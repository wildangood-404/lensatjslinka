<?php

namespace App\Http\Controllers;

use App\Models\PumkBriMitra;
use App\Models\PumkBriSnapshotBulanan;
use App\Services\Pumk\PumkActivityLogger;
use App\Services\Pumk\PumkBriMonthlySnapshotImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PumkBriMonthlySnapshotController extends Controller
{
    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'wilayah' => ['nullable', 'string', 'max:255'],
            'sektor' => ['nullable', 'string', 'max:255'],
            'kolektibilitas' => ['nullable', 'in:lancar,kurang_lancar,diragukan,macet'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'between:1900,2100'],
        ]);
        if (array_key_exists('bulan', $filters) xor array_key_exists('tahun', $filters)) {
            throw ValidationException::withMessages([
                'bulan' => 'Pilih bulan dan tahun laporan secara bersamaan.',
            ]);
        }

        $periods = PumkBriSnapshotBulanan::query()
            ->select(['tahun', 'bulan'])
            ->distinct()
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->get();
        $latestPeriod = $periods->first();
        $selectedYear = (int) ($filters['tahun'] ?? $latestPeriod?->tahun ?? now()->year);
        $selectedMonth = (int) ($filters['bulan'] ?? $latestPeriod?->bulan ?? now()->month);
        $snapshotFilter = static function ($query) use ($selectedYear, $selectedMonth, $filters): void {
            $query->where('tahun', $selectedYear)->where('bulan', $selectedMonth)
                ->when(filled($filters['wilayah'] ?? null), function ($snapshots) use ($filters): void {
                    $keyword = trim((string) $filters['wilayah']);
                    $snapshots->where(fn ($location) => $location
                        ->where('kabupaten_kota', 'like', '%'.$keyword.'%')
                        ->orWhere('provinsi', 'like', '%'.$keyword.'%'));
                })
                ->when(filled($filters['sektor'] ?? null), function ($snapshots) use ($filters): void {
                    $sector = trim((string) $filters['sektor']);
                    $snapshots->where(fn ($query) => $query
                        ->where('sektor_ekonomi_bumn', $sector)
                        ->orWhere('jenis_usaha', $sector));
                })
                ->when(filled($filters['kolektibilitas'] ?? null), function ($snapshots) use ($filters): void {
                    $code = match ($filters['kolektibilitas']) {
                        'lancar' => 'L',
                        'kurang_lancar' => 'KL',
                        'diragukan' => 'D',
                        'macet' => 'M',
                    };
                    $snapshots->where('kolektibilitas_kode', $code);
                });
        };

        $mitraList = PumkBriMitra::query()
            ->whereHas('snapshots', $snapshotFilter)
            ->with(['snapshots' => $snapshotFilter])
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters, $snapshotFilter): void {
                $keyword = trim((string) $filters['q']);
                $query->where(function ($search) use ($keyword, $snapshotFilter): void {
                    $search->where('nama_mitra', 'like', '%'.$keyword.'%')
                        ->orWhereHas('snapshots', function ($snapshots) use ($keyword, $snapshotFilter): void {
                            $snapshotFilter($snapshots);
                            $snapshots->where(fn ($query) => $query
                                ->where('nama_mitra_binaan', 'like', '%'.$keyword.'%')
                                ->orWhere('bri_unit', 'like', '%'.$keyword.'%')
                                ->orWhere('kabupaten_kota', 'like', '%'.$keyword.'%'));
                        });
                });
            })
            ->orderBy('nama_mitra')
            ->paginate(15)
            ->withQueryString();

        foreach ($mitraList as $mitra) {
            $snapshots = $mitra->snapshots;
            $first = $snapshots->first();
            $total = '0.00';
            foreach ($snapshots as $snapshot) {
                $total = bcadd($total, (string) ($snapshot->outstanding ?? $snapshot->saldo_piutang), 2);
            }

            $mitra->setAttribute('report_name', $first?->nama_mitra_binaan ?? $mitra->nama_mitra);
            $mitra->setAttribute('report_unit', $this->uniqueValues($snapshots, 'bri_unit'));
            $mitra->setAttribute('report_region', $this->uniqueLocations($snapshots));
            $mitra->setAttribute('report_sector', $this->uniqueValues($snapshots, 'jenis_usaha', 'sektor_ekonomi_bumn'));
            $mitra->setAttribute('report_collectibility', $this->uniqueValues($snapshots, 'kolektibilitas_bumn', 'kolektibilitas_label'));
            $mitra->setAttribute('report_outstanding', $total);
        }

        $years = $periods->pluck('tahun')->map(fn ($year): int => (int) $year)->unique()->values();
        if (! $years->contains($selectedYear)) {
            $years->push($selectedYear);
        }
        $sectorOptions = PumkBriSnapshotBulanan::query()
            ->where('tahun', $selectedYear)
            ->where('bulan', $selectedMonth)
            ->get(['sektor_ekonomi_bumn', 'jenis_usaha'])
            ->flatMap(fn (PumkBriSnapshotBulanan $snapshot) => [
                $snapshot->sektor_ekonomi_bumn,
                $snapshot->jenis_usaha,
            ])
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('pumk-admin.bri.monthly-mitra-index', [
            'mitraList' => $mitraList,
            'periods' => $periods,
            'sectorOptions' => $sectorOptions,
            'selectedCollectibility' => $filters['kolektibilitas'] ?? '',
            'years' => $years->sortDesc()->values(),
            'months' => self::MONTHS,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
        ]);
    }

    public function create(): View
    {
        return view('pumk-admin.bri.monthly-import', [
            'months' => self::MONTHS,
            'defaultMonth' => (int) old('bulan', now()->month),
            'defaultYear' => (int) old('tahun', now()->year),
        ]);
    }

    public function store(
        Request $request,
        PumkBriMonthlySnapshotImportService $importer,
        PumkActivityLogger $activityLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:1900,2100'],
            'import_file' => ['required', 'file', 'max:15360', 'extensions:xlsx,xls,csv'],
        ], [
            'bulan.required' => 'Bulan laporan wajib dipilih.',
            'bulan.between' => 'Bulan laporan tidak valid.',
            'tahun.required' => 'Tahun laporan wajib diisi.',
            'tahun.between' => 'Tahun laporan harus berada pada rentang 1900-2100.',
            'import_file.required' => 'Pilih file Excel atau CSV yang akan diunggah.',
            'import_file.max' => 'Ukuran file maksimal 15 MB.',
            'import_file.extensions' => 'File harus berformat .xlsx, .xls, atau .csv.',
        ]);

        $file = $request->file('import_file');
        $path = $file?->getRealPath();
        if (! is_string($path) || $path === '') {
            return back()->withErrors([
                'import_file' => 'File unggahan sementara tidak dapat dibaca. Silakan unggah kembali.',
            ])->withInput();
        }

        try {
            $result = $importer->import(
                $path,
                strtolower((string) $file->getClientOriginalExtension()),
                (int) $validated['bulan'],
                (int) $validated['tahun'],
            );
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['import_file' => $exception->getMessage()])->withInput();
        } catch (RuntimeException $exception) {
            return back()->withErrors(['import_file' => $exception->getMessage()])->withInput();
        }

        $activityLogger->record(
            'upload_bri_monthly_snapshot',
            'pumk_bri',
            'Mengunggah snapshot bulanan UMKM Binaan Via BRI.',
            metadata: [
                'bulan' => $result['bulan'],
                'tahun' => $result['tahun'],
                'status' => $result['status'],
                'baris' => $result['baris'],
            ],
        );

        if ($result['status'] === 'needs_review') {
            return redirect()->route('pumk-admin.umkm-binaan-bri.create')
                ->with('import_result', $result)
                ->with('warning', 'Snapshot belum diperbarui karena terdapat identitas yang perlu ditinjau.');
        }

        return redirect()->route('pumk-admin.umkm-binaan-bri.create')
            ->with('import_result', $result)
            ->with('success', 'Snapshot periode berhasil diperbarui.');
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Laporan BRI');
            $sheet->fromArray(PumkBriMonthlySnapshotImportService::TEMPLATE_HEADERS, null, 'A1');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:Q1');
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'template-umkm-binaan-via-bri.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function show(Request $request, PumkBriMitra $mitra): View
    {
        $filters = $request->validate([
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'between:1900,2100'],
        ]);
        if (array_key_exists('bulan', $filters) xor array_key_exists('tahun', $filters)) {
            throw ValidationException::withMessages([
                'bulan' => 'Pilih bulan dan tahun laporan secara bersamaan.',
            ]);
        }

        $history = PumkBriSnapshotBulanan::query()
            ->where('mitra_id', $mitra->id)
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->orderBy('fasilitas_id')
            ->get()
            ->groupBy('fasilitas_id');
        $latest = $history->flatten(1)->first();
        $selectedYear = (int) ($filters['tahun'] ?? $latest?->tahun ?? now()->year);
        $selectedMonth = (int) ($filters['bulan'] ?? $latest?->bulan ?? now()->month);
        $currentSnapshots = $history->flatten(1)
            ->filter(fn (PumkBriSnapshotBulanan $snapshot): bool => $snapshot->tahun === $selectedYear && $snapshot->bulan === $selectedMonth)
            ->values();

        return view('pumk-admin.bri.monthly-mitra-profile', [
            'mitra' => $mitra,
            'historyByFacility' => $history,
            'currentSnapshots' => $currentSnapshots,
            'months' => self::MONTHS,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
        ]);
    }

    /** @param Collection<int, PumkBriSnapshotBulanan> $snapshots */
    private function uniqueValues(Collection $snapshots, string $primary, ?string $fallback = null): string
    {
        return $snapshots
            ->map(fn (PumkBriSnapshotBulanan $snapshot) => $snapshot->{$primary} ?: ($fallback === null ? null : $snapshot->{$fallback}))
            ->filter()
            ->unique()
            ->implode(', ') ?: '—';
    }

    /** @param Collection<int, PumkBriSnapshotBulanan> $snapshots */
    private function uniqueLocations(Collection $snapshots): string
    {
        return $snapshots
            ->map(fn (PumkBriSnapshotBulanan $snapshot) => collect([$snapshot->kabupaten_kota, $snapshot->provinsi])->filter()->implode(', '))
            ->filter()
            ->unique()
            ->implode('; ') ?: '—';
    }
}
