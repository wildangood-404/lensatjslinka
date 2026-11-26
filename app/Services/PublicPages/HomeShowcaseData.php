<?php

namespace App\Services\PublicPages;

use App\Models\NewsItem;
use App\Models\Pillar;
use App\Models\Program;
use App\Models\PumkBriPeriod;
use App\Models\PumkBriRealization;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HomeShowcaseData
{
    private const QUALITY = [
        'lancar' => ['label' => 'Lancar', 'color' => '#16a34a'],
        'kurang_lancar' => ['label' => 'Kurang Lancar', 'color' => '#eab308'],
        'diragukan' => ['label' => 'Diragukan', 'color' => '#f97316'],
        'macet' => ['label' => 'Macet', 'color' => '#dc2626'],
        'bermasalah' => ['label' => 'Bermasalah', 'color' => '#7f1d1d'],
    ];

    public function build(): array
    {
        return [
            'news' => NewsItem::query()
                ->published()
                ->latest('id')
                ->limit(6)
                ->get()
                ->map(fn (NewsItem $item): array => [
                    'title' => $item->title,
                    'caption' => $item->caption ?: Str::limit((string) $item->content, 180),
                    'content' => $item->content ?: $item->caption,
                    'category' => $item->source,
                    'published_date' => $item->published_date,
                    'image_url' => $item->image_url,
                ])
                ->values(),
            'programs' => $this->programData(),
            'regular' => $this->umkmData('reguler'),
            'bri' => $this->briData(),
        ];
    }

    private function programData(): array
    {
        $publicPrograms = Program::query()
            ->where('is_archived', false)
            ->whereIn('status', Program::PUBLIC_STATUSES);

        $pillarCounts = (clone $publicPrograms)
            ->selectRaw('pillar_id, COUNT(*) AS total')
            ->groupBy('pillar_id')
            ->pluck('total', 'pillar_id');

        $pillarColors = [
            'Sosial' => '#2563eb',
            'Ekonomi' => '#f59e0b',
            'Lingkungan' => '#16a34a',
            'Hukum & Tata Kelola' => '#dc2626',
        ];
        $pillars = Pillar::query()->orderBy('id')->get()->keyBy('name');
        $pillarData = collect(array_keys($pillarColors))
            ->map(function (string $name) use ($pillarColors, $pillars, $pillarCounts): array {
                $pillar = $pillars->get($name);

                return [
                    'label' => $name,
                    'color' => $pillar?->color_hex ?: $pillarColors[$name],
                    'total' => (int) ($pillar ? $pillarCounts->get($pillar->id, 0) : 0),
                ];
            });

        $statusLabels = [
            'pending_fase1' => 'Menunggu Review Pengajuan',
            'approved_fase1' => 'Pengajuan Disetujui',
            'pending_fase2' => 'Menunggu Verifikasi',
            'completed' => 'Selesai',
            'proposal_approve' => 'Proposal Disetujui',
            'menunggu_review_survei' => 'Menunggu Review Survei',
            'survei_disetujui' => 'Survei Disetujui',
            'tertunda' => 'Tertunda',
            'pelaksanaan' => 'Pelaksanaan',
            'kasbon_disetujui' => 'Kasbon Disetujui',
            'menunggu_review_laporan_akhir' => 'Menunggu Review Laporan Akhir',
            'selesai' => 'Selesai',
        ];
        $statusCounts = (clone $publicPrograms)
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) (clone $publicPrograms)->count(),
            'pillars' => $pillarData->values(),
            'statuses' => collect($statusLabels)
                ->map(fn (string $label, string $key): array => [
                    'label' => $label,
                    'total' => (int) $statusCounts->get($key, 0),
                ])
                ->filter(fn (array $status): bool => $status['total'] > 0)
                ->values(),
        ];
    }

    private function umkmData(string $category): array
    {
        $mitra = PumkMitra::query()->where('kategori_binaan', $category);
        $loans = PumkPinjaman::query()->whereHas(
            'mitra',
            fn ($query) => $query->where('kategori_binaan', $category),
        );

        return [
            'total_mitra' => (int) (clone $mitra)->count(),
            'total_penyaluran' => (float) (clone $loans)->sum('pinjaman_pokok'),
            'regions' => $this->mitraBreakdown($category, 'region'),
            'sectors' => $this->mitraBreakdown($category, 'sector'),
            'quality' => $this->qualityData($category),
        ];
    }

    private function briData(): array
    {
        $periods = PumkBriPeriod::query()
            ->withSum('realisasi', 'nominal')
            ->orderByDesc('periode_tahun')
            ->limit(8)
            ->get()
            ->map(fn (PumkBriPeriod $period): array => [
                'year' => (string) $period->periode_tahun,
                'rka' => (float) $period->anggaran_rka,
                'realization' => (float) ($period->realisasi_sum_nominal ?? 0),
            ])
            ->reverse()
            ->values();

        return [
            'total_mitra' => (int) PumkMitra::query()->where('kategori_binaan', 'bri')->count(),
            'total_rka' => (float) PumkBriPeriod::query()->sum('anggaran_rka'),
            'total_realization' => (float) PumkBriRealization::query()->sum('nominal'),
            'periods' => $periods,
            'quality' => $this->qualityData('bri'),
        ];
    }

    private function mitraBreakdown(string $category, string $dimension): array
    {
        $label = $dimension === 'region'
            ? "COALESCE(NULLIF(mitra.kabupaten_kota, ''), NULLIF(wilayah.nama, ''), NULLIF(mitra.wilayah_sumber, ''), NULLIF(mitra.provinsi, ''), 'Belum terdata')"
            : "COALESCE(NULLIF(sektor.nama, ''), NULLIF(mitra.sektor_sumber, ''), 'Belum terdata')";

        $query = DB::table('pumk_mitra as mitra')
            ->where('mitra.kategori_binaan', $category);

        if ($dimension === 'region') {
            $query->leftJoin('pumk_wilayah as wilayah', 'wilayah.id', '=', 'mitra.wilayah_id');
        } else {
            $query->leftJoin('pumk_sektor_usaha as sektor', 'sektor.id', '=', 'mitra.sektor_usaha_id');
        }

        return $query
            ->selectRaw("{$label} AS label, COUNT(*) AS total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->orderBy('label')
            ->limit(6)
            ->get()
            ->map(fn (object $row): array => [
                'label' => (string) $row->label,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    private function qualityData(string $category): array
    {
        $counts = PumkPinjaman::query()
            ->where('is_active', true)
            ->where('status', PumkPinjaman::STATUS_AKTIF)
            ->whereHas('mitra', fn ($query) => $query->where('kategori_binaan', $category))
            ->selectRaw('kolektibilitas, COUNT(*) AS total')
            ->groupBy('kolektibilitas')
            ->pluck('total', 'kolektibilitas');

        return collect(self::QUALITY)
            ->map(fn (array $item, string $key): array => [
                'key' => $key,
                'label' => $item['label'],
                'color' => $item['color'],
                'total' => (int) $counts->get($key, 0),
            ])
            ->values()
            ->all();
    }
}
