<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TjslSubmissionStatusFilter
{
    /** @return array<string, string> */
    public static function options(bool $includeDraft): array
    {
        $options = [
            'all' => 'Semua Status Aktif',
            'waiting' => 'Menunggu Pengecekan',
            'approved_phase1' => 'ACC Tahap 1',
            'revision' => 'Perlu Perbaikan',
            'rejected' => 'Ditolak',
            'completed' => 'Selesai',
        ];

        if ($includeDraft) {
            $options = ['all' => 'Semua Status Aktif', 'draft' => 'Draft'] + array_diff_key($options, ['all' => true]);
        }

        return $options;
    }

    /** @return array<string, string> */
    public static function programOptions(bool $includeDraft): array
    {
        $options = [
            'all' => 'Semua Status Aktif',
            'menunggu_review_proposal' => 'Menunggu Review Pengajuan Proposal Program',
            'revisi' => 'Revisi Pengajuan Proposal Program',
            'proposal_approve' => 'Pengajuan Proposal Program Disetujui',
            'menunggu_review_survei' => 'Menunggu Review Verifikasi Program',
            'perbaikan' => 'Revisi Verifikasi Program',
            'survei_disetujui' => 'Verifikasi Program Disetujui',
            'tertunda' => 'Tertunda',
            'pelaksanaan' => 'Pelaksanaan',
            'kasbon_disetujui' => 'Kasbon Disetujui',
            'kasbon_ditolak' => 'Kasbon Ditolak',
            'menunggu_review_laporan_akhir' => 'Menunggu Review Laporan Akhir',
            'revisi_laporan_akhir' => 'Revisi Laporan Akhir',
            'selesai' => 'Selesai',
            'ditolak' => 'Ditolak',
        ];

        if ($includeDraft) {
            $options = ['all' => 'Semua Status Aktif', 'draft' => 'Draft']
                + array_diff_key($options, ['all' => true]);
        }

        return $options;
    }

    public static function selected(Request $request, bool $includeDraft): string
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(array_keys(self::options($includeDraft)))],
        ]);

        return $validated['status'] ?? 'all';
    }

    public static function selectedProgram(Request $request, bool $includeDraft): string
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(array_keys(self::programOptions($includeDraft)))],
        ]);

        return $validated['status'] ?? 'all';
    }

    /** @return list<string>|null */
    public static function statuses(string $filter): ?array
    {
        return match ($filter) {
            'draft' => ['draft'],
            'menunggu_review_proposal' => ['menunggu_review_proposal', 'pending_fase1'],
            'revisi' => ['revisi'],
            'proposal_approve' => ['proposal_approve', 'approved_fase1'],
            'menunggu_review_survei' => ['menunggu_review_survei', 'pending_fase2'],
            'perbaikan' => ['perbaikan'],
            'survei_disetujui' => ['survei_disetujui'],
            'tertunda' => ['tertunda'],
            'pelaksanaan' => ['pelaksanaan'],
            'kasbon_disetujui' => ['kasbon_disetujui'],
            'kasbon_ditolak' => ['kasbon_ditolak'],
            'menunggu_review_laporan_akhir' => ['menunggu_review_laporan_akhir'],
            'revisi_laporan_akhir' => ['revisi_laporan_akhir'],
            'selesai' => ['selesai', 'completed'],
            'ditolak' => ['rejected_fase1', 'ditolak'],
            'waiting' => ['pending_fase1', 'pending_fase2', 'menunggu_review_proposal', 'menunggu_review_survei', 'menunggu_review_laporan_akhir', 'tertunda'],
            'approved_phase1' => ['approved_fase1', 'proposal_approve', 'survei_disetujui', 'pelaksanaan', 'kasbon_disetujui'],
            'revision' => ['revisi', 'perbaikan', 'revisi_laporan_akhir'],
            'rejected' => ['rejected_fase1', 'kasbon_ditolak', 'ditolak'],
            'completed' => ['completed', 'selesai'],
            default => null,
        };
    }

    /** @return list<string> */
    public static function submittedStatuses(): array
    {
        return [
            'pending_fase1', 'approved_fase1', 'pending_fase2', 'rejected_fase1', 'completed',
            'menunggu_review_proposal', 'revisi', 'proposal_approve', 'menunggu_review_survei',
            'perbaikan', 'survei_disetujui', 'tertunda', 'pelaksanaan', 'kasbon_disetujui', 'kasbon_ditolak',
            'menunggu_review_laporan_akhir', 'revisi_laporan_akhir', 'selesai', 'ditolak',
        ];
    }

    /** @return list<string> */
    public static function visibleStatuses(string $filter, bool $includeDraft): array
    {
        $statuses = self::statuses($filter);

        if ($statuses !== null) {
            return $statuses;
        }

        $visible = $includeDraft
            ? ['draft', ...self::submittedStatuses()]
            : self::submittedStatuses();

        return array_values(array_diff($visible, ['rejected_fase1', 'kasbon_ditolak', 'ditolak']));
    }

    public static function apply(Builder $query, string $filter, bool $includeDraft): Builder
    {
        return match ($filter) {
            'draft' => $query->where('status', 'draft'),
            'waiting' => $query->whereIn('status', self::statuses('waiting')),
            'menunggu_review_proposal',
            'revisi',
            'menunggu_review_survei',
            'perbaikan',
            'survei_disetujui',
            'tertunda',
            'pelaksanaan',
            'kasbon_disetujui',
            'kasbon_ditolak',
            'menunggu_review_laporan_akhir',
            'revisi_laporan_akhir',
            'selesai' => $query->whereIn('status', self::statuses($filter)),
            'proposal_approve' => $query
                ->whereIn('status', self::statuses($filter))
                ->where(function (Builder $statusQuery): void {
                    $statusQuery->where('status', '!=', 'approved_fase1')
                        ->orWhereNull('fase2_rejected_reason');
                }),
            'ditolak' => $query->where(function (Builder $statusQuery): void {
                $statusQuery->whereIn('status', ['rejected_fase1', 'ditolak'])
                    ->orWhere(function (Builder $phaseTwoQuery): void {
                        $phaseTwoQuery->where('status', 'approved_fase1')
                            ->whereNotNull('fase2_rejected_reason');
                    });
            }),
            'approved_phase1' => $query
                ->whereIn('status', self::statuses('approved_phase1'))
                ->where(function (Builder $statusQuery): void {
                    $statusQuery->where('status', '!=', 'approved_fase1')
                        ->orWhereNull('fase2_rejected_reason');
                }),
            'revision' => $query->whereIn('status', self::statuses('revision')),
            'rejected' => $query->where(function (Builder $statusQuery): void {
                $statusQuery->where('status', 'rejected_fase1')
                    ->orWhereIn('status', ['ditolak', 'kasbon_ditolak'])
                    ->orWhere(function (Builder $phaseTwoQuery): void {
                        $phaseTwoQuery->where('status', 'approved_fase1')
                            ->whereNotNull('fase2_rejected_reason');
                    });
            }),
            'completed' => $query->whereIn('status', self::statuses('completed')),
            default => $query->whereIn('status', self::visibleStatuses('all', $includeDraft)),
        };
    }
}
