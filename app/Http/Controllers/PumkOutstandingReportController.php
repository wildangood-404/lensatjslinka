<?php

namespace App\Http\Controllers;

use App\Models\PumkPinjaman;
use App\Services\Pumk\PiutangCalculator;
use App\Services\Pumk\PumkCollectibilitySummaryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class PumkOutstandingReportController extends Controller
{
    private const QUALITY_CATEGORIES = [
        'lancar' => 'Lancar',
        'kurang_lancar' => 'Kurang Lancar',
        'diragukan' => 'Diragukan',
        'macet' => 'Macet',
    ];

    private const PROBLEM_CATEGORIES = ['kurang_lancar', 'diragukan', 'macet'];

    public function __invoke(PiutangCalculator $calculator): View
    {
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
            'regionRows' => array_map($this->presentGroup(...), array_values($regions)),
            'regionTotal' => $this->presentGroup($totals),
            'sectorRows' => array_map($this->presentGroup(...), array_values($sectors)),
            'sectorTotal' => $this->presentGroup($totals),
            'qualityRows' => array_map($this->presentAmounts(...), array_values($qualityRows)),
            'qualityTotal' => $this->presentAmounts($qualityTotals, 'Total'),
            'unratedLoans' => $unratedLoans,
        ]);
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
     *  @return array{label:?string,mitra_count:int,principal:string,interest:string,problem_count?:int,problem_principal?:string,problem_interest?:string}
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
     *  @return array{label:?string,mitra_count:int,principal:string,interest:string}
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
