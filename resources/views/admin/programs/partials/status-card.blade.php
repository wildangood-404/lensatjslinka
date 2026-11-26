@php
    $pillarName = $program->effective_pilar;
    $pillarSlug = \Illuminate\Support\Str::slug((string) $pillarName);
    $pillarStyles = [
        'hukum-tata-kelola' => ['background' => 'bg-red-600', 'backgroundColor' => '#dc2626'],
        'ekonomi' => ['background' => 'bg-amber-500', 'backgroundColor' => '#f59e0b'],
        'sosial' => ['background' => 'bg-blue-600', 'backgroundColor' => '#2563eb'],
        'lingkungan' => ['background' => 'bg-emerald-600', 'backgroundColor' => '#059669'],
    ];
    $pillarStyle = $pillarStyles[$pillarSlug] ?? ['background' => 'bg-slate-600', 'backgroundColor' => '#475569'];
    $isExternal = in_array($program->jenis_pengajuan, ['external_stakeholder', 'External (Stakeholder)'], true)
        && filled($program->nama_mitra);
    $partnerName = $isExternal ? $program->nama_mitra : 'TJSL';
    $pillarName = $pillarName ?: 'Belum ditentukan';
    $programDate = $program->updated_at ?? $program->submitted_at;
    $followUpStages = [
        'proposal_approve' => ['stage-2-edit', 'Lanjutkan Pengisian'],
        'survei_disetujui' => ['stage-3', 'Lanjutkan Pengisian'],
        'tertunda' => ['stage-4', 'Lanjutkan Pengisian'],
        'pelaksanaan' => ['stage-5', 'Lanjutkan Pengisian'],
        'revisi' => ['stage-1', 'Perbaiki Berkas'],
        'perbaikan' => ['stage-2-edit', 'Perbaiki Berkas'],
        'revisi_laporan_akhir' => ['stage-5', 'Perbaiki Berkas'],
    ];
    $isSuperAdminCard = \Illuminate\Support\Str::startsWith($detailRouteName, 'superadmin.');
    $followUp = $isSuperAdminCard ? null : ($followUpStages[$program->status] ?? null);
    $followUpUrl = $followUp
        ? route($detailRouteName, $program).'#'.$followUp[0]
        : route($detailRouteName, $program);
    $followUpLabel = $followUp[1] ?? 'Lihat Detail';
    $followUpClass = match ($followUpLabel) {
        'Perbaiki Berkas' => 'bg-white text-orange-700 hover:bg-orange-50 border border-white',
        'Lanjutkan Pengisian' => 'bg-white text-emerald-600 hover:bg-emerald-50 border border-white',
        default => 'bg-white text-amber-600 hover:bg-amber-50 border border-white',
    };
@endphp

<article
    data-program-status="{{ $program->status }}"
    class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 rounded-xl p-5 sm:p-6 text-white shadow-sm {{ $pillarStyle['background'] }}"
    @style(['background-color:'.$pillarStyle['backgroundColor']])
>
    <a href="{{ route($detailRouteName, $program) }}" class="flex-1 min-w-0 space-y-1.5 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
        <h2 class="text-lg font-bold text-white leading-tight">{{ $partnerName }}</h2>
        <p class="text-sm text-white/95 font-medium">{{ $program->nama_program }}</p>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/20 text-white backdrop-blur-xs">
            Pilar: {{ $pillarName }}
        </span>
    </a>

    <div class="flex flex-col items-start md:items-end gap-2 shrink-0">
        @include('admin.partials.status-badge', [
            'status' => $program->status,
            'phaseTwoRejected' => filled($program->fase2_rejected_reason),
            'programCard' => true,
        ])
        <a href="{{ $followUpUrl }}" class="inline-flex min-w-[180px] items-center justify-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold shadow-sm transition whitespace-nowrap {{ $followUpClass }}">
            {{ $followUpLabel }}
        </a>
        <time class="text-xs text-white/90 font-medium whitespace-nowrap">
            {{ $programDate?->format('H.i d/m/Y') ?? '-' }}
        </time>
    </div>
</article>
