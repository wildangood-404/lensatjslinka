<x-layouts.admin title="Status Program">
    @include('admin.partials.status-badge-styles')

    <style>
        .sa-page{max-width:1100px;margin:auto}.sa-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin:0 0 28px}.sa-page h1{margin:0;font-size:30px}.sa-category{margin:5px 0 0;color:#5d6470;font-size:14px}.sa-list{display:grid;gap:19px}.sa-back{display:inline-block;margin-top:24px;color:#2653ff;font-size:14px;font-weight:600;text-decoration:none}.sa-empty{padding:32px;border:1px solid #ddd;border-radius:10px;background:#fff;color:#60636b;text-align:center}@media(max-width:700px){.sa-heading{align-items:flex-start;flex-direction:column}}
    </style>

    <div class="sa-page">
        <div class="sa-heading">
            <div>
                <h1>Program TJSL INKA</h1>

                @if($selectedPillar)
                    <p class="sa-category">Kategori: {{ $selectedPillar->name }}</p>
                @endif
            </div>
        </div>

        @include('admin.partials.status-filter', [
            'routeName' => 'superadmin.programs.index',
            'filterId' => 'superadmin-program-status-filter',
            'selectedPillar' => $selectedPillar,
            'resultCount' => $programs->count(),
            'resultLabel' => 'program',
        ])

        <div class="sa-list">
            @forelse ($programs as $program)
                @include('admin.programs.partials.status-card', [
                    'program' => $program,
                    'detailRouteName' => 'superadmin.programs.show',
                ])
            @empty
                <p class="sa-empty">
                    Belum ada program dengan status {{ $statusOptions[$statusFilter] }}{{ $selectedPillar ? ' pada kategori '.$selectedPillar->name : '' }}.
                </p>
            @endforelse
        </div>

        @if($selectedPillar)
            <a href="{{ route('superadmin.programs.index') }}" class="sa-back">
                Lihat Semua Kategori
            </a>
        @endif
    </div>
</x-layouts.admin>
