<x-layouts.admin title="Status Program">
    @include('admin.partials.status-badge-styles')

    <div class="mx-auto max-w-275">
        <div class="mb-7">
            <div>
                <h1 class="text-3xl font-bold">Program TJSL INKA</h1>

                @if ($selectedPillar)
                    <p class="mt-1 text-sm text-slate-600">Kategori: {{ $selectedPillar->name }}</p>
                @endif
            </div>
        </div>

        @include('admin.partials.status-filter', [
            'routeName' => 'admin.programs.index',
            'filterId' => 'admin-program-status-filter',
            'selectedPillar' => $selectedPillar,
            'resultCount' => $programs->count(),
            'resultLabel' => 'program',
        ])

        <div class="space-y-5">
            @forelse ($programs as $program)
                @include('admin.programs.partials.status-card', [
                    'program' => $program,
                    'detailRouteName' => 'admin.programs.show',
                ])
            @empty
                <p class="rounded-xl border bg-white p-8 text-center text-slate-500">
                    Belum ada program dengan status {{ $statusOptions[$statusFilter] }}{{ $selectedPillar ? ' pada kategori '.$selectedPillar->name : '' }}.
                </p>
            @endforelse
        </div>

        @if ($selectedPillar)
            <a href="{{ route('admin.programs.index') }}" class="text-action-primary mt-6 inline-block text-sm font-semibold">
                Lihat Semua Kategori
            </a>
        @endif
    </div>
</x-layouts.admin>
