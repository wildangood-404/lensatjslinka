<x-layouts.admin title="Home Super Admin">
<style>
.sa-wrap{width:100%}.sa-title{font-size:46px;line-height:1.15;font-weight:800;letter-spacing:-1.1px;margin:0 0 48px}.sa-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}.sa-stat{height:153px;box-sizing:border-box;border-radius:11px;padding:13px;color:#fff;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 3px 4px #0004}.sa-stat.gray{background:#9e9e9e}.sa-stat.approved{background:#6d28d9}.sa-stat strong{font-size:46px;font-weight:600;line-height:1}.sa-stat span{font-size:20px;font-weight:600;line-height:1.5}.sa-section{margin-top:66px}.sa-section h2{margin:0 0 24px;font-size:32px;line-height:1.25;font-weight:500}.sa-program-list{display:grid;gap:10px}.sa-program-category{display:flex;min-height:62px;box-sizing:border-box;align-items:center;justify-content:space-between;gap:20px;padding:12px 16px;color:#fff;text-decoration:none;border-radius:6px;box-shadow:0 2px 5px #0002}.sa-program-category:hover{filter:brightness(.94)}.sa-program-category strong{font-size:18px;font-weight:600}.sa-program-category span{font-size:14px;font-weight:500;white-space:nowrap}.sa-empty{padding:14px;border:1px solid #ddd;border-radius:5px;background:#fff;color:#60636b}@media(max-width:950px){.sa-title{font-size:36px}.sa-stats{grid-template-columns:1fr 1fr}}@media(max-width:560px){.sa-title{font-size:29px}.sa-stats{grid-template-columns:1fr}.sa-program-category{align-items:flex-start;flex-direction:column;gap:4px}}
</style>
<div class="sa-wrap">
    <h1 class="sa-title">Dashboard SuperAdmin</h1>
    <div class="sa-stats">
        <div class="sa-stat gray"><span>Program TJSL yang Belum dilihat</span><strong>{{ $stats['program_pending'] }}</strong></div>
        <div class="sa-stat approved"><span>Program TJSL yang sudah di Approved</span><strong>{{ $stats['program_approved'] }}</strong></div>
    </div>
    <section class="sa-section">
        <h2>Program TJSL</h2>
        <div class="sa-program-list">
            @foreach($pillars as $pillar)
                @php
                    $programCount = (int) ($pillarCounts[$pillar->id] ?? 0);
                @endphp

                @if($programCount > 0)
                    <a
                        class="sa-program-category"
                        data-pillar="{{ $pillar->slug }}"
                        href="{{ route('superadmin.programs.index', ['pillar' => $pillar->slug]) }}"
                        @style(['background:'.$pillar->color_hex])
                    >
                        <strong>Program TJSL {{ $pillar->name }}</strong>
                        <span>{{ $programCount }} Program</span>
                    </a>
                @endif
            @endforeach

            @if($pillarCounts->isEmpty())
                <p class="sa-empty">Belum ada program.</p>
            @endif
        </div>
    </section>
</div>
</x-layouts.admin>
