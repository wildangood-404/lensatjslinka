@php
    $statusValue = $status ?? 'draft';
    $phaseTwoRejected = (bool) ($phaseTwoRejected ?? false);
    $programCard = (bool) ($programCard ?? false);
    $visualStatus = $phaseTwoRejected && $statusValue === 'approved_fase1'
        ? 'rejected_fase2'
        : $statusValue;
    $statusColor = match ($visualStatus) {
        'pending_fase1', 'pending_fase2', 'menunggu_review_proposal', 'menunggu_review_survei',
        'menunggu_review_laporan_akhir' => 'waiting',
        'revisi', 'perbaikan', 'revisi_laporan_akhir' => 'revision',
        'tertunda' => 'waiting',
        'rejected_fase1', 'rejected_fase2', 'kasbon_ditolak', 'ditolak' => 'rejected',
        'approved_fase1', 'proposal_approve', 'survei_disetujui', 'pelaksanaan', 'kasbon_disetujui', 'pelaksanaan_program' => 'approved',
        'completed', 'selesai' => 'completed',
        default => 'draft',
    };
    $programCardTextClass = match ($statusColor) {
        'approved' => 'text-blue-600',
        'completed' => 'text-emerald-600',
        'waiting', 'revision' => 'text-amber-600',
        'rejected' => 'text-red-600',
        default => 'text-slate-700',
    };
    $statusLabel = [
        'draft' => 'Draft',
        'pending_fase1' => 'Waiting',
        'rejected_fase1' => 'Rejected',
        'approved_fase1' => 'Approved',
        'pending_fase2' => 'Waiting Fase 2',
        'completed' => 'Completed',
        'rejected_fase2' => 'BAST Ditolak',
        'menunggu_review_proposal' => 'Menunggu Review Pengajuan Proposal Program',
        'revisi' => 'Revisi Pengajuan Proposal Program',
        'proposal_approve' => 'Pengajuan Proposal Program Disetujui',
        'menunggu_review_survei' => 'Menunggu Review Verifikasi Program',
        'perbaikan' => 'Revisi Verifikasi Program',
        'survei_disetujui' => 'Verifikasi Program Disetujui',
        'tertunda' => 'Tertunda',
        'pelaksanaan' => 'Pelaksanaan',
        'kasbon_disetujui' => 'Kasbon Disetujui',
        'pelaksanaan_program' => 'Pelaksanaan Program',
        'kasbon_ditolak' => 'Kasbon Ditolak',
        'menunggu_review_laporan_akhir' => 'Menunggu Review Laporan Akhir',
        'revisi_laporan_akhir' => 'Revisi Laporan Akhir',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
    ][$visualStatus] ?? ucfirst(str_replace('_', ' ', $visualStatus));
@endphp
<span class="submission-status-badge{{ $programCard ? ' submission-status-program-card submission-status-'.$statusColor.' '.$programCardTextClass.' inline-flex items-center gap-1.5 rounded-full bg-white px-3.5 py-1.5 text-xs font-semibold shadow-xs whitespace-nowrap' : ' submission-status-'.$statusColor }}" data-status="{{ $statusValue }}">
    <span>{{ $statusLabel }}</span>
    @switch($visualStatus)
        @case('draft')
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 16.5-.7 4.2 4.2-.7L19 8.5 15.5 5 4 16.5Z"/><path d="m13.8 6.7 3.5 3.5M3.3 20.7h17.4"/></svg>
            @break
        @case('pending_fase1')
        @case('pending_fase2')
        @case('menunggu_review_proposal')
        @case('menunggu_review_survei')
        @case('menunggu_review_laporan_akhir')
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v5h4M9 2h6M12 2v3M18.5 6.5l1.5-1.5"/></svg>
            @break
        @case('rejected_fase1')
        @case('rejected_fase2')
        @case('revisi')
        @case('perbaikan')
        @case('revisi_laporan_akhir')
        @case('ditolak')
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16"/><path d="m8 8 8 8m0-8-8 8"/></svg>
            @break
        @case('approved_fase1')
        @case('completed')
        @case('proposal_approve')
        @case('survei_disetujui')
        @case('pelaksanaan')
        @case('kasbon_disetujui')
        @case('pelaksanaan_program')
        @case('selesai')
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.7 2.7L16.5 9"/></svg>
            @break
    @endswitch
</span>
