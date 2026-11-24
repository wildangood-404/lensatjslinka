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
@endphp

<a
    href="{{ route($detailRouteName, $program) }}"
    data-program-status="{{ $program->status }}"
    class="flex items-center justify-between gap-4 rounded-xl p-4 text-white shadow-sm sm:p-5 {{ $pillarStyle['background'] }}"
    @style(['background-color:'.$pillarStyle['backgroundColor']])
>
    <div class="flex min-w-0 flex-col gap-1">
        <h2 class="text-lg font-bold text-white">{{ $partnerName }}</h2>
        <div class="flex flex-wrap items-center gap-2">
            <p class="text-sm text-white/95">{{ $program->nama_program }}</p>
            <span class="inline-flex items-center rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-medium text-white">
                Pilar: {{ $pillarName }}
            </span>
        </div>
    </div>

    <div class="flex shrink-0 flex-col items-end gap-1.5">
        @include('admin.partials.status-badge', [
            'status' => $program->status,
            'phaseTwoRejected' => filled($program->fase2_rejected_reason),
            'programCard' => true,
        ])
        <time class="text-right text-xs text-white/90">
            {{ $programDate?->format('H.i d/m/Y') ?? '-' }}
        </time>
    </div>
</a>
