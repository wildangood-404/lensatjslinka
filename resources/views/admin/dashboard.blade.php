<x-layouts.admin title="Dashboard Admin">
<style>
    .admin-home{max-width:1100px;margin:0 auto}.admin-home h1{margin:0 0 48px;font-size:46px;line-height:1.2;font-weight:700;letter-spacing:-1px}.pillar-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:40px}.pillar-card{height:175px;display:flex;flex-direction:column;justify-content:space-between;padding:18px 20px;border-radius:11px;color:#fff;box-shadow:0 3px 4px rgba(0,0,0,.25)}.pillar-card h2{margin:0;font-size:23px;font-weight:600}.pillar-card strong{font-size:48px;font-weight:500;line-height:1}.admin-list-section{margin-top:68px}.admin-list-title{margin:0 0 24px;font-size:31px;font-weight:500}.program-list{display:grid;gap:12px}.program-category{display:flex;min-height:62px;box-sizing:border-box;align-items:center;justify-content:space-between;gap:20px;border-radius:6px;padding:12px 16px;color:#fff;text-decoration:none;box-shadow:0 2px 4px rgba(0,0,0,.24)}.program-category:hover{filter:brightness(.94)}.program-category strong{font-size:18px;font-weight:600}.program-category span{font-size:14px;font-weight:500;white-space:nowrap}.empty-row{padding:14px;border:1px solid #ddd;border-radius:5px;background:#fff;color:#60636b}@media(max-width:1100px){.pillar-grid{gap:20px}}@media(max-width:800px){.admin-home h1{font-size:34px}.pillar-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:480px){.pillar-grid{grid-template-columns:1fr}.admin-home h1{font-size:29px}.program-category{align-items:flex-start;flex-direction:column;gap:4px}}
</style>
<div class="admin-home">
    <h1>Dashboard Admin TJSL</h1>
    <section class="pillar-grid" aria-label="Ringkasan program per pilar">
        @foreach($pillars as $pillar)
            <article class="pillar-card" @style(['background:'.$pillar->color_hex])>
                <h2>{{ $pillar->name }}</h2>
                <strong>{{ $pillarCounts[$pillar->id] ?? 0 }}</strong>
            </article>
        @endforeach
    </section>

    <section class="admin-list-section">
        <h2 class="admin-list-title">Program TJSL</h2>
        <div class="program-list">
            @forelse($pillars as $pillar)
                @php
                    $categoryPrograms = $programs->where('pillar_id', $pillar->id);
                @endphp
                @if($categoryPrograms->isNotEmpty())
                    <a
                        class="program-category"
                        data-pillar="{{ $pillar->slug }}"
                        href="{{ route('admin.programs.index', ['pillar' => $pillar->slug]) }}"
                        @style(['background:'.$pillar->color_hex])
                    >
                        <strong>Program TJSL {{ $pillar->name }}</strong>
                        <span>{{ $categoryPrograms->count() }} Program</span>
                    </a>
                @endif
            @empty
            @endforelse
            @if($programs->isEmpty())<p class="empty-row">Belum ada Program TJSL.</p>@endif
        </div>
    </section>

</div>
</x-layouts.admin>
