<?php

namespace App\Http\Controllers;

use App\Models\PumkPinjaman;
use App\Models\PumkBriSnapshotBulanan;
use App\Services\Pumk\PiutangCalculator;
use App\Services\Pumk\PumkCollectibilitySummaryService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PumkOutstandingReportController extends Controller
{
    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    private const BRI_QUALITY_CATEGORIES = [
        'lancar' => 'Lancar',
        'kurang_lancar' => 'Kurang Lancar',
        'diragukan' => 'Diragukan',
        'macet' => 'Macet',
    ];

    private const QUALITY_SQL = "CASE UPPER(TRIM(COALESCE(NULLIF(TRIM(kolektibilitas_bumn), ''), NULLIF(TRIM(kolektibilitas_label), ''), kolektibilitas_kode)))
        WHEN 'L' THEN 'lancar' WHEN 'LANCAR' THEN 'lancar'
        WHEN 'KL' THEN 'kurang_lancar' WHEN 'KURANG LANCAR' THEN 'kurang_lancar'
        WHEN 'D' THEN 'diragukan' WHEN 'DIRAGUKAN' THEN 'diragukan'
        WHEN 'M' THEN 'macet' WHEN 'MACET' THEN 'macet'
        ELSE NULL END";

    private const REALIZATION_SUM_SQL = 'COALESCE(SUM(COALESCE(jumlah_realisasi, pinjaman_sumber, 0)), 0)';

    private const OUTSTANDING_SUM_SQL = 'COALESCE(SUM(COALESCE(outstanding, saldo_piutang, 0)), 0)';

    private const QUALITY_CATEGORIES = [
        'lancar' => 'Lancar',
        'kurang_lancar' => 'Kurang Lancar',
        'diragukan' => 'Diragukan',
        'macet' => 'Macet',
        'bermasalah' => 'Bermasalah',
    ];

    private const PROBLEM_CATEGORIES = ['kurang_lancar', 'diragukan', 'macet', 'bermasalah'];

    public function __invoke(Request $request, PiutangCalculator $calculator): View
    {
        $sourceCategory = $request->routeIs('pumk-admin.umkm-binaan-bri.*') ? 'bri' : 'reguler';
        $pageTitle = $sourceCategory === 'bri'
            ? 'Report Outstanding UMKM Binaan Via BRI'
            : 'Report Outstanding UMKM Binaan';
        if ($sourceCategory === 'bri') {
            return $this->briSnapshotReport($request, $pageTitle);
        }

        $regions = [];
        $sectors = [];
        $totals = $this->emptyGroup('Total');
        $qualityTotals = $this->emptyAmounts();
        $qualityRows = [];
        foreach (self::QUALITY_CATEGORIES as $category => $label) {
            $qualityRows[$category] = $this->emptyAmounts($label);
        }
        $unratedLoans = 0;

        $excludedSourceKeys = config('pumk.recap_excluded_source_keys', []);
        PumkPinjaman::query()
            ->where(function (Builder $query): void {
                $query->where(function (Builder $active): void {
                    $active->where('status', PumkPinjaman::STATUS_AKTIF)->where('is_active', true);
                })->orWhere('status', PumkPinjaman::STATUS_LUNAS);
            })
            ->when($excludedSourceKeys !== [], fn (Builder $query) => $query->whereNotIn('source_key', $excludedSourceKeys))
            ->whereHas('mitra', fn (Builder $query) => $query->where('kategori_binaan', $sourceCategory))
            ->with(['mitra.wilayah', 'mitra.sektorUsaha', 'saldoAwal', 'angsuran'])
            ->chunkById(200, function ($loans) use (&$regions, &$sectors, &$totals, &$qualityRows, &$qualityTotals, &$unratedLoans, $calculator): void {
                foreach ($loans as $loan) {
                    $mitra = $loan->mitra;
                    $position = $calculator->hitungUntukPinjaman($loan);
                    $category = PumkCollectibilitySummaryService::normalizeCategory($position['kolektibilitas'] ?? null);
                    $principal = (string) ($position['sisa_pokok'] ?? '0.00');
                    $interest = (string) ($position['sisa_bunga'] ?? '0.00');
                    $mitraId = (int) $loan->mitra_id;

                    $this->addAmounts($totals, $mitraId, $principal, $interest);

                    $regionLabel = $mitra->kabupaten_kota
                        ?: $mitra->wilayah?->nama
                        ?: $mitra->wilayah_sumber
                        ?: 'Wilayah tidak diketahui';
                    $sectorLabel = $mitra->sektorUsaha?->nama
                        ?: $mitra->sektor_sumber
                        ?: $mitra->jenis_usaha
                        ?: 'Sektor tidak diketahui';

                    $regions[$regionLabel] ??= $this->emptyGroup($regionLabel);
                    $sectors[$sectorLabel] ??= $this->emptyGroup($sectorLabel);
                    $this->addAmounts($regions[$regionLabel], $mitraId, $principal, $interest);
                    $this->addAmounts($sectors[$sectorLabel], $mitraId, $principal, $interest);

                    if (isset(self::QUALITY_CATEGORIES[$category])) {
                        $this->addAmounts($qualityRows[$category], $mitraId, $principal, $interest);
                        $this->addAmounts($qualityTotals, $mitraId, $principal, $interest);
                    } else {
                        $unratedLoans++;
                    }

                    if (in_array($category, self::PROBLEM_CATEGORIES, true)) {
                        $this->addAmounts($totals['problem'], $mitraId, $principal, $interest);
                        $this->addAmounts($regions[$regionLabel]['problem'], $mitraId, $principal, $interest);
                        $this->addAmounts($sectors[$sectorLabel]['problem'], $mitraId, $principal, $interest);
                    }
                }
            });

        ksort($regions, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($sectors, SORT_NATURAL | SORT_FLAG_CASE);

        return view('pumk-admin.umkm-binaan.report-outstanding', [
            'pageTitle' => $pageTitle,
            'regionRows' => array_map($this->presentGroup(...), array_values($regions)),
            'regionTotal' => $this->presentGroup($totals),
            'sectorRows' => array_map($this->presentGroup(...), array_values($sectors)),
            'sectorTotal' => $this->presentGroup($totals),
            'qualityRows' => array_map($this->presentAmounts(...), array_values($qualityRows)),
            'qualityTotal' => $this->presentAmounts($qualityTotals, 'Total'),
            'unratedLoans' => $unratedLoans,
        ]);
    }

    private function briSnapshotReport(Request $request, string $pageTitle): View
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

        $periods = PumkBriSnapshotBulanan::query()
            ->select(['tahun', 'bulan'])
            ->distinct()
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->get();
        $latestPeriod = $periods->first();
        $selectedYear = (int) ($filters['tahun'] ?? $latestPeriod?->tahun ?? now()->year);
        $selectedMonth = (int) ($filters['bulan'] ?? $latestPeriod?->bulan ?? now()->month);
        $snapshotScope = static fn (): Builder => PumkBriSnapshotBulanan::query()
            ->where('tahun', $selectedYear)
            ->where('bulan', $selectedMonth);

        $regionExpression = "COALESCE(NULLIF(TRIM(bri_unit), ''), NULLIF(TRIM(kabupaten_kota), ''), NULLIF(TRIM(wilayah_sumber), ''), NULLIF(TRIM(provinsi), ''), 'Wilayah tidak diketahui')";
        $sectorExpression = "COALESCE(NULLIF(TRIM(sektor_ekonomi_bumn), ''), 'Sektor tidak diketahui')";
        $regionRows = $this->briSnapshotGroups($snapshotScope, $regionExpression);
        $sectorRows = $this->briSnapshotGroups($snapshotScope, $sectorExpression);

        $qualityGroups = $snapshotScope()
            ->selectRaw(self::QUALITY_SQL.' as quality')
            ->selectRaw('COUNT(DISTINCT mitra_id) as mitra_count')
            ->selectRaw(self::REALIZATION_SUM_SQL.' as realization')
            ->selectRaw(self::OUTSTANDING_SUM_SQL.' as outstanding')
            ->whereRaw('('.self::QUALITY_SQL.') IS NOT NULL')
            ->groupByRaw(self::QUALITY_SQL)
            ->get()
            ->keyBy('quality');
        $qualityRows = [];
        foreach (self::BRI_QUALITY_CATEGORIES as $category => $label) {
            $aggregate = $qualityGroups->get($category);
            $qualityRows[] = [
                'label' => $label,
                'mitra_count' => (int) ($aggregate->mitra_count ?? 0),
                'realization' => $this->decimalAmount($aggregate->realization ?? '0'),
                'outstanding' => $this->decimalAmount($aggregate->outstanding ?? '0'),
            ];
        }

        $years = $periods->pluck('tahun')->map(fn ($year): int => (int) $year)->unique()->values();
        if (! $years->contains($selectedYear)) {
            $years->push($selectedYear);
        }

        return view('pumk-admin.bri.report-outstanding', [
            'pageTitle' => $pageTitle,
            'periods' => $periods,
            'months' => self::MONTHS,
            'years' => $years->sortDesc()->values(),
            'selectedMonth' => $selectedMonth,
            'selectedYear' => $selectedYear,
            'regionRows' => $regionRows,
            'regionTotal' => ['label' => 'Total', ...$this->totalBriRows($regionRows)],
            'sectorRows' => $sectorRows,
            'sectorTotal' => ['label' => 'Total', ...$this->totalBriRows($sectorRows)],
            'qualityRows' => $qualityRows,
            'qualityTotal' => ['label' => 'Total', ...$this->totalBriRows($qualityRows)],
        ]);
    }

    /** @return list<array{label:string,mitra_count:int,realization:string,outstanding:string}> */
    private function briSnapshotGroups(Closure $snapshotScope, string $groupExpression): array
    {
        return $snapshotScope()
            ->selectRaw($groupExpression.' as label')
            ->selectRaw('COUNT(DISTINCT mitra_id) as mitra_count')
            ->selectRaw(self::REALIZATION_SUM_SQL.' as realization')
            ->selectRaw(self::OUTSTANDING_SUM_SQL.' as outstanding')
            ->groupByRaw($groupExpression)
            ->orderBy('label')
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->label,
                'mitra_count' => (int) $row->mitra_count,
                'realization' => $this->decimalAmount($row->realization),
                'outstanding' => $this->decimalAmount($row->outstanding),
            ])
            ->all();
    }

    /**
     * @param  list<array{label:string,mitra_count:int,realization:string,outstanding:string}>  $rows
     * @return array{mitra_count:int,realization:string,outstanding:string}
     */
    private function totalBriRows(array $rows): array
    {
        return array_reduce($rows, static fn (array $total, array $row): array => [
            'mitra_count' => $total['mitra_count'] + $row['mitra_count'],
            'realization' => bcadd($total['realization'], $row['realization'], 2),
            'outstanding' => bcadd($total['outstanding'], $row['outstanding'], 2),
        ], ['mitra_count' => 0, 'realization' => '0.00', 'outstanding' => '0.00']);
    }

    private function decimalAmount(mixed $amount): string
    {
        return bcadd((string) ($amount ?? '0'), '0', 2);
    }

    /** @return array<string, mixed> */
    private function emptyGroup(string $label): array
    {
        return [
            ...$this->emptyAmounts(),
            'label' => $label,
            'problem' => $this->emptyAmounts(),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyAmounts(?string $label = null): array
    {
        return [
            'label' => $label,
            'mitra_ids' => [],
            'principal' => '0.00',
            'interest' => '0.00',
        ];
    }

    /** @param array<string, mixed> $group */
    private function addAmounts(array &$group, int $mitraId, string $principal, string $interest): void
    {
        $group['mitra_ids'][$mitraId] = true;
        $group['principal'] = bcadd($group['principal'], $principal, 2);
        $group['interest'] = bcadd($group['interest'], $interest, 2);
    }

    /** @param array<string, mixed> $group
     * @return array{label:?string,mitra_count:int,principal:string,interest:string,problem_count?:int,problem_principal?:string,problem_interest?:string}
     */
    private function presentGroup(array $group): array
    {
        return [
            'label' => $group['label'],
            ...$this->presentAmounts($group),
            'problem_count' => count($group['problem']['mitra_ids']),
            'problem_principal' => $group['problem']['principal'],
            'problem_interest' => $group['problem']['interest'],
        ];
    }

    /** @param array<string, mixed> $amounts
     * @return array{label:?string,mitra_count:int,principal:string,interest:string}
     */
    private function presentAmounts(array $amounts, ?string $label = null): array
    {
        return [
            'label' => $label ?? $amounts['label'],
            'mitra_count' => count($amounts['mitra_ids']),
            'principal' => $amounts['principal'],
            'interest' => $amounts['interest'],
        ];
    }
}
