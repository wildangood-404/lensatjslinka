<section
    id="home-showcase"
    class="home-showcase mx-auto w-full max-w-7xl px-4 pb-14 pt-8 sm:px-6 lg:px-8"
    x-data="homeShowcase($el)"
    @keydown.escape.window="closeNews()"
>
    @php($showCharts = $showCharts ?? true)
    <style>[x-cloak]{display:none!important}.home-showcase{font-family:Poppins,sans-serif}</style>
    <script type="application/json" data-home-showcase-data>{!! json_encode($homeShowcase, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>

    <div class="mb-7 flex flex-wrap items-end justify-between gap-5">
        <div class="max-w-3xl">
            <p class="mb-2 text-sm font-bold uppercase tracking-[.18em] text-blue-700">LENSA TJSL INKA</p>
            <h1 class="text-3xl font-bold leading-tight tracking-tight text-slate-950 sm:text-4xl">Portal Informasi TJSL &amp; PUMK PT INKA (Persero)</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">Ikuti kabar kegiatan, program pemberdayaan, dan capaian Tanggung Jawab Sosial dan Lingkungan PT INKA (Persero).</p>
        </div>
        @if ($showCharts)
            <div class="inline-flex max-w-full items-center gap-1 rounded-2xl border border-slate-200/80 bg-slate-100 p-1.5 shadow-sm" role="tablist" aria-label="Pilih tampilan Home">
                <button type="button" role="tab" @click="setMode('news')" :aria-selected="mode === 'news'" :class="mode === 'news' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'" class="inline-flex min-h-11 items-center gap-2 whitespace-nowrap rounded-xl px-3.5 text-sm font-semibold transition sm:px-4">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 4.75h14A1.25 1.25 0 0 1 20.25 6v14H5A1.25 1.25 0 0 1 3.75 18.75V6A1.25 1.25 0 0 1 5 4.75ZM7 8h10M7 11h10M7 14h6"/></svg>
                    <span>News &amp; Kegiatan TJSL</span>
                </button>
                <button type="button" role="tab" @click="setMode('charts')" :aria-selected="mode === 'charts'" :class="mode === 'charts' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'" class="inline-flex min-h-11 items-center gap-2 whitespace-nowrap rounded-xl px-3.5 text-sm font-semibold transition sm:px-4">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.25V12h4v7.25h-4Zm5.5 0V5h4v14.25h-4Zm5.5 0v-9h4v9h-4Z"/></svg>
                    <span>Grafik &amp; Capaian</span>
                </button>
            </div>
        @else
            <p class="text-sm font-bold text-blue-700">News &amp; Kegiatan TJSL</p>
        @endif
    </div>

    <div x-show="mode === 'news'" x-transition.opacity>
        <div class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-xl shadow-slate-900/5" @mouseenter="pauseNews = true" @mouseleave="pauseNews = false">
            <template x-if="news.length">
                <div class="grid min-h-[410px] lg:grid-cols-[1.15fr_.85fr]">
                    <div class="relative min-h-[270px] bg-slate-200 lg:min-h-[470px]">
                        <img :src="currentNews.image_url || fallbackImage" :alt="currentNews.title" x-on:error="$event.target.src = fallbackImage; $event.target.onerror = null" class="absolute inset-0 h-full w-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/65 via-transparent to-slate-950/10"></div>
                        <div class="absolute left-5 top-5 flex flex-wrap gap-2 sm:left-7 sm:top-7">
                            <span class="rounded-full bg-white/95 px-3 py-1.5 text-xs font-bold text-blue-800 shadow-sm" x-text="currentNews.category"></span>
                            <span class="rounded-full bg-slate-950/70 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur" x-text="currentNews.published_date"></span>
                        </div>
                        <div class="absolute bottom-5 left-5 right-5 flex items-center justify-between sm:bottom-7 sm:left-7 sm:right-7">
                            <div class="flex items-center gap-2" aria-label="Pilih berita">
                                <template x-for="(item, index) in news" :key="`${item.title}-${index}`">
                                    <button type="button" @click="goToNews(index)" :aria-label="`Tampilkan berita ${index + 1}`" :aria-current="newsIndex === index ? 'true' : 'false'" :class="newsIndex === index ? 'w-8 bg-white' : 'w-2.5 bg-white/60 hover:bg-white/90'" class="h-2.5 rounded-full transition-all"></button>
                                </template>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="previousNews()" aria-label="Berita sebelumnya" class="grid h-11 w-11 place-items-center rounded-full border border-white/40 bg-slate-950/30 text-3xl leading-none text-white backdrop-blur transition hover:bg-slate-950/60">‹</button>
                                <button type="button" @click="nextNews()" aria-label="Berita selanjutnya" class="grid h-11 w-11 place-items-center rounded-full border border-white/40 bg-slate-950/30 text-3xl leading-none text-white backdrop-blur transition hover:bg-slate-950/60">›</button>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col justify-center p-6 sm:p-9 lg:p-10">
                        <p class="mb-3 text-xs font-bold uppercase tracking-[.16em] text-blue-700">Kabar terbaru</p>
                        <h2 class="text-2xl font-bold leading-tight text-slate-950 sm:text-3xl" x-text="currentNews.title"></h2>
                        <p class="mt-4 line-clamp-3 text-sm leading-7 text-slate-600 sm:text-base" x-text="currentNews.caption"></p>
                        <div class="mt-7">
                            <button type="button" @click="openNews(currentNews)" class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200">
                                Baca Selengkapnya
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
            <div x-show="!news.length" class="grid min-h-[320px] place-items-center px-6 py-12 text-center">
                <div><p class="font-semibold text-slate-800">Belum ada berita yang diterbitkan.</p><p class="mt-2 text-sm text-slate-500">Berita terbaru akan tampil di sini setelah dipublikasikan.</p></div>
            </div>
        </div>

        <div class="mb-4 mt-10 flex items-end justify-between gap-4">
            <div><p class="text-xs font-bold uppercase tracking-[.16em] text-blue-700">Jelajahi kabar</p><h2 class="mt-1 text-2xl font-bold text-slate-950">Berita terbaru</h2></div>
            <span class="hidden text-sm text-slate-500 sm:block">Cerita kegiatan dan dampak TJSL INKA</span>
        </div>
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            <template x-for="(item, index) in news.slice(0, 3)" :key="`latest-${item.title}-${index}`">
                <button type="button" @click="openNews(item)" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-sm transition hover:-translate-y-1 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-blue-200">
                    <div class="relative h-52 overflow-hidden bg-slate-100">
                        <img :src="item.image_url || fallbackImage" :alt="item.title" x-on:error="$event.target.src = fallbackImage; $event.target.onerror = null" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                        <span class="absolute left-4 top-4 rounded-full bg-white/95 px-3 py-1 text-xs font-bold text-blue-800" x-text="item.category"></span>
                    </div>
                    <div class="p-5">
                        <p class="text-xs font-medium text-slate-500" x-text="item.published_date"></p>
                        <h3 class="mt-2 line-clamp-2 text-lg font-bold leading-snug text-slate-900" x-text="item.title"></h3>
                        <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600" x-text="item.caption"></p>
                        <span class="mt-4 inline-flex items-center gap-1 text-sm font-bold text-blue-700">Baca berita <span aria-hidden="true">→</span></span>
                    </div>
                </button>
            </template>
            <div x-show="!news.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-sm text-slate-500 md:col-span-2 lg:col-span-3">Berita terbaru akan segera hadir.</div>
        </div>
    </div>

    @if ($showCharts)
    <div x-show="mode === 'charts'" x-transition.opacity>
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex max-w-full flex-wrap gap-2" role="tablist" aria-label="Kategori grafik">
                <button type="button" @click="goToChart(0)" :aria-selected="chartIndex === 0" :class="chartIndex === 0 ? 'border-blue-700 bg-blue-700 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300'" class="rounded-xl border px-3.5 py-2.5 text-sm font-semibold transition">1. Program TJSL</button>
                <button type="button" @click="goToChart(1)" :aria-selected="chartIndex === 1" :class="chartIndex === 1 ? 'border-blue-700 bg-blue-700 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300'" class="rounded-xl border px-3.5 py-2.5 text-sm font-semibold transition">2. UMKM Binaan</button>
                <button type="button" @click="goToChart(2)" :aria-selected="chartIndex === 2" :class="chartIndex === 2 ? 'border-blue-700 bg-blue-700 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300'" class="rounded-xl border px-3.5 py-2.5 text-sm font-semibold transition">3. UMKM Binaan Via BRI</button>
            </div>
            <div class="flex gap-2">
                <button type="button" @click="previousChart()" aria-label="Grafik sebelumnya" class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-2xl text-slate-700 shadow-sm transition hover:bg-slate-50">‹</button>
                <button type="button" @click="nextChart()" aria-label="Grafik selanjutnya" class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-2xl text-slate-700 shadow-sm transition hover:bg-slate-50">›</button>
            </div>
        </div>

        <template x-if="chartIndex === 0">
            <section class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <article class="rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-700 to-indigo-800 p-5 text-white shadow-sm"><p class="text-sm font-medium text-blue-100">Total Program TJSL</p><strong class="mt-3 block text-3xl font-bold" x-text="number(programs.total)"></strong><p class="mt-1 text-xs text-blue-100">Program publik yang sedang berjalan atau telah disetujui</p></article>
                    <template x-for="pillar in programs.pillars" :key="pillar.label">
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-600" x-text="pillar.label"></p><strong class="mt-3 block text-3xl font-bold text-slate-950" x-text="number(pillar.total)"></strong><p class="mt-1 text-xs text-slate-500">Program per pilar</p></article>
                    </template>
                </div>
                <div class="grid gap-5 lg:grid-cols-2">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-lg font-bold text-slate-900">Distribusi Program per Pilar</h2><p class="mt-1 text-sm text-slate-500">Sosial, Ekonomi, Lingkungan, dan Hukum &amp; Tata Kelola</p><div class="mt-4 h-72"><canvas data-home-chart="program-pillars" role="img" aria-label="Grafik distribusi program per pilar"></canvas></div></article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-lg font-bold text-slate-900">Status Program</h2><p class="mt-1 text-sm text-slate-500">Status program dari data publik terbaru</p><div class="mt-4 h-72"><canvas data-home-chart="program-status" role="img" aria-label="Grafik status program"></canvas></div></article>
                </div>
            </section>
        </template>

        <template x-if="chartIndex === 1">
            <section class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <article class="rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-700 p-5 text-white shadow-sm"><p class="text-sm font-medium text-emerald-100">Total UMKM Binaan</p><strong class="mt-3 block text-3xl font-bold" x-text="number(regular.total_mitra)"></strong><p class="mt-1 text-xs text-emerald-100">Mitra kategori reguler</p></article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Nilai Penyaluran</p><strong class="mt-3 block text-2xl font-bold text-slate-950 sm:text-3xl" x-text="rupiah(regular.total_penyaluran)"></strong><p class="mt-1 text-xs text-slate-500">Akumulasi pokok pinjaman tercatat</p></article>
                </div>
                <div class="grid gap-5 lg:grid-cols-2">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-lg font-bold text-slate-900">Mitra berdasarkan Wilayah</h2><p class="mt-1 text-sm text-slate-500">Enam wilayah dengan jumlah mitra terbanyak</p><div class="mt-4 h-72"><canvas data-home-chart="regular-regions" role="img" aria-label="Grafik UMKM berdasarkan wilayah"></canvas></div></article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-lg font-bold text-slate-900">Mitra berdasarkan Sektor Usaha</h2><p class="mt-1 text-sm text-slate-500">Enam sektor dengan jumlah mitra terbanyak</p><div class="mt-4 h-72"><canvas data-home-chart="regular-sectors" role="img" aria-label="Grafik UMKM berdasarkan sektor usaha"></canvas></div></article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:col-span-2"><h2 class="text-lg font-bold text-slate-900">Kualitas Piutang UMKM Binaan</h2><p class="mt-1 text-sm text-slate-500">Pinjaman aktif menurut kolektibilitas</p><div class="mx-auto mt-4 h-72 max-w-xl"><canvas data-home-chart="regular-quality" role="img" aria-label="Grafik kualitas piutang UMKM Binaan"></canvas></div></article>
                </div>
            </section>
        </template>

        <template x-if="chartIndex === 2">
            <section class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article class="rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 p-5 text-white shadow-sm"><p class="text-sm font-medium text-amber-50">Anggaran RKA PUMK BRI</p><strong class="mt-3 block text-2xl font-bold sm:text-3xl" x-text="rupiah(bri.total_rka)"></strong></article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Total Realisasi PUMK BRI</p><strong class="mt-3 block text-2xl font-bold text-slate-950 sm:text-3xl" x-text="rupiah(bri.total_realization)"></strong></article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">UMKM Binaan Via BRI</p><strong class="mt-3 block text-3xl font-bold text-slate-950" x-text="number(bri.total_mitra)"></strong><p class="mt-1 text-xs text-slate-500">Mitra kategori BRI</p></article>
                </div>
                <div class="grid gap-5 lg:grid-cols-2">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-lg font-bold text-slate-900">RKA vs Realisasi per Periode Tahun</h2><p class="mt-1 text-sm text-slate-500">Perbandingan anggaran dan penyaluran PUMK BRI</p><div class="mt-4 h-72"><canvas data-home-chart="bri-periods" role="img" aria-label="Grafik RKA dan realisasi PUMK BRI per periode"></canvas></div></article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-lg font-bold text-slate-900">Kualitas Piutang Via BRI</h2><p class="mt-1 text-sm text-slate-500">Pinjaman aktif menurut kolektibilitas</p><div class="mt-4 h-72"><canvas data-home-chart="bri-quality" role="img" aria-label="Grafik kualitas piutang UMKM Binaan Via BRI"></canvas></div></article>
                </div>
            </section>
        </template>
    </div>
    @endif

    <div x-show="selectedNews" x-transition.opacity class="fixed inset-0 z-[70] grid place-items-center overflow-y-auto bg-slate-950/70 p-4 sm:p-8" @click.self="closeNews()" role="presentation">
        <article x-show="selectedNews" x-transition class="my-auto w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="home-news-title">
            <div class="relative h-56 bg-slate-200 sm:h-80">
                <img :src="selectedNews ? (selectedNews.image_url || fallbackImage) : fallbackImage" :alt="selectedNews ? selectedNews.title : ''" x-on:error="$event.target.src = fallbackImage; $event.target.onerror = null" class="h-full w-full object-cover">
                <button type="button" @click="closeNews()" aria-label="Tutup berita" class="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/95 text-2xl text-slate-700 shadow hover:bg-white">×</button>
            </div>
            <div class="max-h-[55vh] overflow-y-auto p-6 sm:p-8">
                <div class="mb-3 flex flex-wrap gap-2">
                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800" x-text="selectedNews ? selectedNews.category : ''"></span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600" x-text="selectedNews ? selectedNews.published_date : ''"></span>
                </div>
                <h2 id="home-news-title" class="text-2xl font-bold leading-tight text-slate-950 sm:text-3xl" x-text="selectedNews ? selectedNews.title : ''"></h2>
                <p class="mt-4 font-medium leading-7 text-slate-700" x-text="selectedNews ? selectedNews.caption : ''"></p>
                <p class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-600" x-text="selectedNews ? selectedNews.content : ''"></p>
            </div>
        </article>
    </div>
</section>
