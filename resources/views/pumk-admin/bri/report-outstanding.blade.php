<x-layouts.pumk-admin :title="$pageTitle">
    @php
        $formatRupiah = static function (string $amount): string {
            $negative = str_starts_with($amount, '-');
            [$whole, $cents] = array_pad(explode('.', ltrim($amount, '-'), 2), 2, '00');
            $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) ?? $whole;

            return ($negative ? '-' : '').'Rp '.$grouped.($cents === '00' ? '' : ','.$cents);
        };
        $qualityClasses = [
            'Lancar' => 'is-lancar',
            'Kurang Lancar' => 'is-kurang-lancar',
            'Diragukan' => 'is-diragukan',
            'Macet' => 'is-macet',
        ];
    @endphp

    <style>
        .outstanding-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:24px}
        .outstanding-actions{display:flex;gap:10px}
        .outstanding-filter{display:block;margin-bottom:20px;padding:16px}
        .outstanding-filter-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
        .outstanding-section{margin-top:24px;overflow:hidden}
        .outstanding-section h2{margin:0;padding:20px 22px 14px;font-size:20px;font-weight:700;color:#0f172a}
        .outstanding-period{margin:0 0 12px;color:#64748b;font-size:12px}
        .outstanding-table-wrap{overflow-x:auto}
        .outstanding-table{width:100%;border-collapse:collapse;font-size:13px}
        .outstanding-table th,.outstanding-table td{border:1px solid #e2e8f0;padding:10px 12px}
        .outstanding-table thead th{background:#f1f5f9;color:#334155;text-align:center;vertical-align:middle;font-weight:700}
        .outstanding-table tbody td{color:#334155}
        .outstanding-table .number{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
        .outstanding-table .center{text-align:center}
        .outstanding-table tfoot td{border-top:2px solid #94a3b8;background:#f8fafc;font-weight:700}
        .outstanding-table .empty-row{text-align:center;padding:22px;color:#64748b}
        .quality-badge{display:inline-flex;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:700}
        .quality-badge.is-lancar{background:#dcfce7;color:#166534}
        .quality-badge.is-kurang-lancar{background:#fef9c3;color:#854d0e}
        .quality-badge.is-diragukan{background:#ffedd5;color:#9a3412}
        .quality-badge.is-macet{background:#fee2e2;color:#991b1b}
        @media(max-width:700px){.outstanding-heading{flex-direction:column}.outstanding-actions{width:100%}.outstanding-actions button{width:100%}.outstanding-filter-grid{grid-template-columns:1fr}}
        @media print{.pumk-header,.pumk-sidebar,.pumk-mobile-toggle,.pumk-overlay,.outstanding-actions,.outstanding-filter{display:none!important}.pumk-main,.pumk-shell.is-collapsed .pumk-main{margin:0;padding:0}.pumk-shell{background:#fff}.pumk-page{max-width:none}.outstanding-section{break-inside:avoid;box-shadow:none}.outstanding-table th,.outstanding-table tfoot td{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
    </style>

    <div class="pumk-page">
        <div class="outstanding-heading">
            <div>
                <h1 class="pumk-page-title">{{ $pageTitle }}</h1>
                <p class="pumk-page-subtitle">Rekapitulasi penyaluran dan outstanding berdasarkan snapshot laporan bulanan UMKM Binaan Via BRI.</p>
            </div>
            <div class="outstanding-actions">
                <button type="button" class="pumk-secondary-button" onclick="window.print()">Cetak</button>
            </div>
        </div>

        <form method="GET" action="{{ route('pumk-admin.umkm-binaan-bri.report-outstanding') }}" class="pumk-card outstanding-filter">
            <div class="outstanding-filter-grid">
                <div class="pumk-field">
                    <label for="bulan">Bulan laporan</label>
                    <select id="bulan" name="bulan" class="pumk-select" required>
                        @foreach($months as $month => $label)
                            <option value="{{ $month }}" @selected($selectedMonth === $month)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pumk-field">
                    <label for="tahun">Tahun laporan</label>
                    <select id="tahun" name="tahun" class="pumk-select" required>
                        @foreach($years as $year)
                            <option value="{{ $year }}" @selected($selectedYear === $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
                <button type="submit" class="pumk-primary-button">Tampilkan</button>
                <a href="{{ route('pumk-admin.umkm-binaan-bri.report-outstanding') }}" class="pumk-secondary-button">Periode terbaru</a>
            </div>
        </form>

        <p class="outstanding-period">Periode laporan: <strong>{{ $months[$selectedMonth] }} {{ $selectedYear }}</strong></p>

        <section class="pumk-card outstanding-section" aria-labelledby="outstanding-region-title">
            <h2 id="outstanding-region-title">Rekapitulasi Outstanding Berdasarkan Wilayah BRI</h2>
            <div class="outstanding-table-wrap">
                <table class="outstanding-table">
                    <thead>
                    <tr>
                        <th>No</th>
                        <th>Wilayah (KC BRI)</th>
                        <th>Jumlah UMKM</th>
                        <th>Nilai Realisasi (Penyaluran)</th>
                        <th>Outstanding / Piutang</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($regionRows as $row)
                        <tr>
                            <td class="center">{{ $loop->iteration }}</td>
                            <td>{{ $row['label'] }}</td>
                            <td class="number">{{ number_format($row['mitra_count'], 0, ',', '.') }}</td>
                            <td class="number">{{ $formatRupiah($row['realization']) }}</td>
                            <td class="number">{{ $formatRupiah($row['outstanding']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="empty-row" colspan="5">Belum ada data snapshot pada periode ini.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="number">{{ number_format($regionTotal['mitra_count'], 0, ',', '.') }}</td>
                        <td class="number">{{ $formatRupiah($regionTotal['realization']) }}</td>
                        <td class="number">{{ $formatRupiah($regionTotal['outstanding']) }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="pumk-card outstanding-section" aria-labelledby="outstanding-sector-title">
            <h2 id="outstanding-sector-title">Rekapitulasi Outstanding Berdasarkan Sektor Usaha</h2>
            <div class="outstanding-table-wrap">
                <table class="outstanding-table">
                    <thead>
                    <tr>
                        <th>No</th>
                        <th>Sektor Usaha</th>
                        <th>Jumlah UMKM</th>
                        <th>Nilai Realisasi (Penyaluran)</th>
                        <th>Outstanding / Piutang</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($sectorRows as $row)
                        <tr>
                            <td class="center">{{ $loop->iteration }}</td>
                            <td>{{ $row['label'] }}</td>
                            <td class="number">{{ number_format($row['mitra_count'], 0, ',', '.') }}</td>
                            <td class="number">{{ $formatRupiah($row['realization']) }}</td>
                            <td class="number">{{ $formatRupiah($row['outstanding']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="empty-row" colspan="5">Belum ada data snapshot pada periode ini.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="number">{{ number_format($sectorTotal['mitra_count'], 0, ',', '.') }}</td>
                        <td class="number">{{ $formatRupiah($sectorTotal['realization']) }}</td>
                        <td class="number">{{ $formatRupiah($sectorTotal['outstanding']) }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="pumk-card outstanding-section" aria-labelledby="outstanding-quality-title">
            <h2 id="outstanding-quality-title">Rekapitulasi Kualitas Piutang</h2>
            <div class="outstanding-table-wrap">
                <table class="outstanding-table">
                    <thead>
                    <tr>
                        <th>No</th>
                        <th>Kategori Kualitas Piutang</th>
                        <th>Jumlah UMKM</th>
                        <th>Nilai Realisasi (Penyaluran)</th>
                        <th>Outstanding / Piutang</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($qualityRows as $row)
                        <tr>
                            <td class="center">{{ $loop->iteration }}</td>
                            <td><span @class(['quality-badge', $qualityClasses[$row['label']]])>{{ $row['label'] }}</span></td>
                            <td class="number">{{ number_format($row['mitra_count'], 0, ',', '.') }}</td>
                            <td class="number">{{ $formatRupiah($row['realization']) }}</td>
                            <td class="number">{{ $formatRupiah($row['outstanding']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="number">{{ number_format($qualityTotal['mitra_count'], 0, ',', '.') }}</td>
                        <td class="number">{{ $formatRupiah($qualityTotal['realization']) }}</td>
                        <td class="number">{{ $formatRupiah($qualityTotal['outstanding']) }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    </div>
</x-layouts.pumk-admin>
