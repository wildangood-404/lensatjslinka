<?php

namespace App\Services\Pumk;

use App\Models\PumkPinjaman;
use Illuminate\Support\Carbon;

final class PumkLoanSnapshotService
{
    public function synchronize(PumkPinjaman $loan, array $data, PiutangCalculator $calculator): void
    {
        if (! filter_var($data['financial_snapshot'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || $this->hasCompleteSchedule($data)) {
            $calculator->sinkronkanCache($loan);

            return;
        }

        $this->save($loan, $data);
    }

    /** @param array<string, mixed> $data */
    public function saveImported(PumkPinjaman $loan, array $data): void
    {
        $this->save($loan, $data);
    }

    /** @param array<string, mixed> $data */
    private function save(PumkPinjaman $loan, array $data): void
    {
        $principal = $data['sisa_pokok'] ?? $loan->sisa_pokok;
        $interest = $data['sisa_bunga'] ?? $loan->sisa_bunga;
        $total = $data['total_sisa'] ?? $loan->total_sisa;
        if ($total === null && $principal !== null && $interest !== null) {
            $total = bcadd((string) $principal, (string) $interest, 2);
        }
        $asOf = Carbon::now('Asia/Jakarta');
        $snapshot = [
            'sisa_pokok' => $principal,
            'sisa_bunga' => $interest,
            'total_sisa' => $total,
            'kolektibilitas' => $data['kolektibilitas'] ?? $loan->kolektibilitas,
            'total_pokok_masuk' => '0.00',
            'total_bunga_masuk' => '0.00',
            'total_denda_masuk' => '0.00',
            'manual_snapshot' => true,
            'formula_sumber' => [
                'tanggal_acuan' => $asOf->toDateString(),
                'sisa_pokok_raw' => $principal,
                'sisa_bunga_raw' => $interest,
                'total_sisa_raw' => $total,
            ],
        ];

        $loan->forceFill([
            'sisa_pokok' => $principal,
            'sisa_bunga' => $interest,
            'total_sisa' => $total,
            'kolektibilitas' => $snapshot['kolektibilitas'],
            'denda' => $data['denda'] ?? $loan->denda ?? '0.00',
            'status_pinjaman' => $data['status_pinjaman'] ?? $loan->status_pinjaman ?? 'aktif',
            'source_updated_at' => $asOf,
            'baseline_sumber' => $snapshot,
            'calculated_at' => null,
        ])->save();
    }

    /** @param array<string, mixed> $data */
    private function hasCompleteSchedule(array $data): bool
    {
        return filled($data['mulai_angsuran'] ?? null)
            && filled($data['nilai_angsuran_bulanan'] ?? null)
            && filled($data['pinjaman_pokok'] ?? null)
            && filled($data['pinjaman_bunga'] ?? null);
    }
}
