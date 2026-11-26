import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;
const homeCharts = new WeakMap();

Alpine.data('homeShowcase', (root) => ({
    root,
    data: JSON.parse(root.querySelector('[data-home-showcase-data]')?.textContent || '{}'),
    mode: 'news',
    newsIndex: 0,
    chartIndex: 0,
    pauseNews: false,
    selectedNews: null,
    newsTimer: null,
    chartTimer: null,
    get news() {
        return this.data.news || [];
    },
    get currentNews() {
        return this.news[this.newsIndex] || {};
    },
    get programs() {
        return this.data.programs || { total: 0, pillars: [], statuses: [] };
    },
    get regular() {
        return this.data.regular || { total_mitra: 0, total_penyaluran: 0, regions: [], sectors: [], quality: [] };
    },
    get bri() {
        return this.data.bri || { total_mitra: 0, total_rka: 0, total_realization: 0, periods: [], quality: [] };
    },
    fallbackImage: '/images/news/tjsl-placeholder.svg',
    init() {
        this.newsTimer = window.setInterval(() => {
            if (this.mode === 'news' && !this.pauseNews && this.news.length > 1) this.nextNews();
        }, 5000);
        this.chartTimer = window.setInterval(() => {
            if (this.mode === 'charts') this.nextChart();
        }, 6000);
    },
    destroy() {
        window.clearInterval(this.newsTimer);
        window.clearInterval(this.chartTimer);
        this.destroyCharts();
    },
    setMode(mode) {
        if (this.mode === mode) return;
        this.mode = mode;
        if (mode === 'charts') {
            this.$nextTick(() => this.renderCharts());
        } else {
            this.destroyCharts();
        }
    },
    goToNews(index) {
        if (!this.news.length) return;
        this.newsIndex = (index + this.news.length) % this.news.length;
    },
    nextNews() {
        this.goToNews(this.newsIndex + 1);
    },
    previousNews() {
        this.goToNews(this.newsIndex - 1);
    },
    goToChart(index) {
        this.chartIndex = (index + 3) % 3;
        this.destroyCharts();
        this.$nextTick(() => this.renderCharts());
    },
    nextChart() {
        this.goToChart(this.chartIndex + 1);
    },
    previousChart() {
        this.goToChart(this.chartIndex - 1);
    },
    openNews(news) {
        this.selectedNews = news;
    },
    closeNews() {
        this.selectedNews = null;
    },
    number(value) {
        return new Intl.NumberFormat('id-ID').format(Number(value) || 0);
    },
    rupiah(value) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(Number(value) || 0);
    },
    destroyCharts() {
        (homeCharts.get(this.root) || []).forEach((chart) => {
            chart.stop();
            chart.destroy();
        });
        homeCharts.delete(this.root);
    },
    renderCharts() {
        if (this.mode !== 'charts') return;
        this.destroyCharts();

        const colors = (items) => items.map((item) => item.color);
        const labels = (items) => items.map((item) => item.label);
        const totals = (items) => items.map((item) => item.total);
        const make = (name, type, chartLabels, datasets, options = {}) => {
            const canvas = this.root.querySelector(`[data-home-chart="${name}"]`);
            if (!canvas) return;
            const chart = new Chart(canvas, {
                type,
                data: { labels: chartLabels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: {
                        legend: { position: type === 'doughnut' ? 'bottom' : 'top' },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${context.dataset.label ? `${context.dataset.label}: ` : ''}${this.number(context.raw)}`,
                            },
                        },
                    },
                    ...options,
                },
            });
            homeCharts.set(this.root, [...(homeCharts.get(this.root) || []), chart]);
        };
        const barOptions = {
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
        };

        if (this.chartIndex === 0) {
            make('program-pillars', 'doughnut', labels(this.programs.pillars), [{
                data: totals(this.programs.pillars),
                backgroundColor: colors(this.programs.pillars),
                borderColor: '#ffffff',
                borderWidth: 3,
            }]);
            make('program-status', 'bar', labels(this.programs.statuses), [{
                label: 'Jumlah program',
                data: totals(this.programs.statuses),
                backgroundColor: '#3569d4',
                borderRadius: 6,
            }], { ...barOptions, indexAxis: 'y' });
        } else if (this.chartIndex === 1) {
            make('regular-regions', 'bar', labels(this.regular.regions), [{
                label: 'Mitra',
                data: totals(this.regular.regions),
                backgroundColor: '#0f9f78',
                borderRadius: 6,
            }], { ...barOptions, indexAxis: 'y' });
            make('regular-sectors', 'bar', labels(this.regular.sectors), [{
                label: 'Mitra',
                data: totals(this.regular.sectors),
                backgroundColor: '#4f46e5',
                borderRadius: 6,
            }], { ...barOptions, indexAxis: 'y' });
            make('regular-quality', 'doughnut', labels(this.regular.quality), [{
                data: totals(this.regular.quality),
                backgroundColor: colors(this.regular.quality),
                borderColor: '#ffffff',
                borderWidth: 3,
            }]);
        } else {
            const periods = this.bri.periods || [];
            make('bri-periods', 'bar', periods.map((period) => period.year), [
                { label: 'Anggaran RKA', data: periods.map((period) => period.rka), backgroundColor: '#f59e0b', borderRadius: 5 },
                { label: 'Realisasi', data: periods.map((period) => period.realization), backgroundColor: '#2563eb', borderRadius: 5 },
            ], {
                scales: {
                    x: { beginAtZero: true },
                    y: { beginAtZero: true, ticks: { callback: (value) => this.rupiah(value) } },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${this.rupiah(context.raw)}`,
                        },
                    },
                },
            });
            make('bri-quality', 'doughnut', labels(this.bri.quality), [{
                data: totals(this.bri.quality),
                backgroundColor: colors(this.bri.quality),
                borderColor: '#ffffff',
                borderWidth: 3,
            }]);
        }
    },
}));

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-pillar-hero]').forEach((hero) => {
        const slides = JSON.parse(hero.dataset.slides || '[]');
        const title = hero.querySelector('[data-pillar-title]');
        const description = hero.querySelector('[data-pillar-description]');
        let index = 0;
        if (!slides.length) return;
        window.setInterval(() => {
            title.parentElement.classList.add('opacity-0', 'translate-y-2');
            window.setTimeout(() => {
                index = (index + 1) % slides.length;
                title.textContent = slides[index].title;
                description.textContent = slides[index].description;
                title.parentElement.classList.remove('opacity-0', 'translate-y-2');
            }, 350);
        }, 4200);
    });
    document.querySelectorAll('[data-menu-button]').forEach((button) => button.addEventListener('click', () => document.querySelector('[data-mobile-menu]')?.classList.toggle('hidden')));
    document.querySelectorAll('[data-faq-button]').forEach((button) => button.addEventListener('click', () => {
        const answer = button.nextElementSibling;
        const isExpanded = button.getAttribute('aria-expanded') === 'true';

        button.setAttribute('aria-expanded', String(!isExpanded));
        answer?.classList.toggle('hidden', isExpanded);
    }));
    const adminSidebar = document.querySelector('[data-admin-sidebar]');
    const adminOverlay = document.querySelector('[data-admin-overlay]');
    document.querySelectorAll('[data-admin-menu-button]').forEach((button) => button.addEventListener('click', () => {
        adminSidebar?.classList.toggle('-translate-x-full');
        adminOverlay?.classList.toggle('hidden');
    }));
    adminOverlay?.addEventListener('click', () => {
        adminSidebar?.classList.add('-translate-x-full');
        adminOverlay.classList.add('hidden');
    });

    document.querySelector('[data-add-target]')?.addEventListener('click', () => {
        const wrapper = document.querySelector('[data-repeat-targets]');
        const row = document.createElement('div');
        row.className = 'mb-2 flex gap-2';
        row.innerHTML = '<textarea name="targets[]" required class="min-h-16 flex-1 rounded bg-[#eeeeee] p-3"></textarea><button type="button" class="rounded bg-action-light px-4 text-white" data-remove-row>Hapus</button>';
        wrapper?.append(row);
    });
    document.querySelector('[data-add-detail]')?.addEventListener('click', () => {
        const wrapper = document.querySelector('[data-repeat-details]');
        const index = wrapper?.querySelectorAll('fieldset').length ?? 0;
        const fieldset = document.createElement('fieldset');
        fieldset.className = 'mt-7 rounded-lg border p-4';
        fieldset.innerHTML = `<legend class="px-2 text-xl font-semibold">Rincian ${index + 1}</legend>${[['rincian_kegiatan','Rincian Kegiatan'],['penerima_bantuan','Penerima Bantuan'],['jenis_bantuan','Jenis Bantuan'],['quality','Quality'],['nominal_bantuan','Nominal Bantuan']].map(([name,label]) => `<label class="grid gap-3 py-2 md:grid-cols-[220px_1fr]"><span>${label}</span><input required name="details[${index}][${name}]" type="${name === 'nominal_bantuan' ? 'number' : 'text'}" class="h-11 rounded bg-[#eeeeee] px-3"></label>`).join('')}<label class="grid gap-3 py-2 md:grid-cols-[220px_1fr]"><span>Foto Dokumentasi</span><input type="file" multiple name="details[${index}][photos][]" accept="image/*" class="rounded bg-[#eeeeee] p-2"></label><button type="button" data-remove-row class="mt-2 rounded bg-action-light px-4 py-2 text-white">Hapus Rincian</button>`;
        wrapper?.append(fieldset);
    });
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-remove-row]')) event.target.closest('[data-remove-row]').parentElement.remove();
    });
});
