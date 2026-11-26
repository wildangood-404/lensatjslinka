<x-layouts.pumk-admin title="Report Outstanding UMKM Binaan">
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
        .outstanding-section{margin-top:24px;overflow:hidden}
        .outstanding-section h2{margin:0;padding:20px 22px 14px;font-size:20px;font-weight:700;color:#0f172a}
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
        .outstanding-note{margin-top:16px;color:#64748b;font-size:13px}
        @media(max-width:700px){.outstanding-heading{flex-direction:column}.outstanding-actions{width:100%}.outstanding-actions button{width:100%}}
        @media print{.pumk-header,.pumk-sidebar,.pumk-mobile-toggle,.pumk-overlay,.outstanding-actions{display:none!important}.pumk-main,.pumk-shell.is-collapsed .pumk-main{margin:0;padding:0}.pumk-shell{background:#fff}.pumk-page{max-width:none}.outstanding-section{break-inside:avoid;box-shadow:none}.outstanding-table th,.outstanding-table tfoot td{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
    </style>

    <div class="pumk-page">
        <div class="outstanding-heading">
            <div>
                <h1 class="pumk-page-title">Report Outstanding UMKM Binaan</h1>
                <p class="pumk-page-subtitle">Rekap saldo pokok dan bunga berdasarkan wilayah, sektor usaha, dan kualitas piutang.</p>
            </div>
            <div class="outstanding-actions">
                <button type="button" class="pumk-secondary-button" onclick="window.print()">Cetak</button>
            </div>
        </div>

        <section class="pumk-card outstanding-section" aria-labelledby="outstanding-region-title">
            <h2 id="outstanding-region-title">Rekapitulasi Outstanding Berdasarkan Wilayah</h2>
            <div class="outstanding-table-wrap">
                <table class="outstanding-table">
                    <thead>
                    <tr>
                        <th rowspan="2">No</th>
                        <th rowspan="2">Wilayah</th>
                        <th rowspan="2">Jumlah UMKM Binaan</th>
                        <th rowspan="2">Nilai Pokok Piutang (Rp)</th>
                        <th rowspan="2">Bunga (Rp)</th>
                        <th colspan="3">Bermasalah</th>
                    </tr>
                    <tr>
                        <th>Jumlah UMKM Binaan</th>
                        <th>Nilai Pokok Piutang (Rp)</th>
                        <th>Bunga (Rp)</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($regionRows as $row)
                        <tr>
                            <td class="center">{{ $loop->iteration }}</td>
                            <td>{{ $row['label'] }}</td>
                            <td class="number">{{ number_format($row['mitra_count'], 0, ',', '.') }}</td>
                            <td class="number">{{ $formatRupiah($row['principal']) }}</td>
                            <td class="number">{{ $formatRupiah($row['interest']) }}</td>
                            <td class="number">{{ number_format($row['problem_count'], 0, ',', '.') }}</td>
                            <td class="number">{{ $formatRupiah($row['problem_principal']) }}</td>
                            <td class="number">{{ $formatRupiah($row['problem_interest']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="empty-row" colspan="8">Belum ada data outstanding untuk ditampilkan.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="number">{{ number_format($regionTotal['mitra_count'], 0, ',', '.') }}</td>
                        <td class="number">{{ $formatRupiah($regionTotal['principal']) }}</td>
                        <td class="number">{{ $formatRupiah($regionTotal['interest']) }}</td>
                        <td class="number">{{ number_format($regionTotal['problem_count'], 0, ',', '.') }}</td>
                        <td class="number">{{ $formatRupiah($regionTotal['problem_principal']) }}</td>
                        <td class="number">{{ $formatRupiah($regionTotal['problem_interest']) }}</td>
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
                        <th rowspan="2">No</th>
                        <th rowspan="2">Sektor Usaha</th>
                        <th rowspan="2">Jumlah UMKM Binaan</th>
                        <th rowspan="2">Nilai Pokok Piutang (Rp)</th>
                        <th rowspan="2">Bunga (Rp)</th>
                        <th colspan="3">Bermasalah</th>
                    </tr>
                    <tr>
                        <th>Jumlah UMKM Binaan</th>
                        <th>Nilai Pokok Piutang (Rp)</th>
                        <th>Bunga (Rp)</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($sectorRows as $row)
                        <tr>
                            <td class="center">{{ $loop->iteration }}</td>
                            <td>{{ $row['label'] }}</td>
                            <td class="number">{{ number_format($row['mitra_count'], 0, ',', '.') }}</td>
                            <td class="number">{{ $formatRupiah($row['principal']) }}</td>
                            <td class="number">{{ $formatRupiah($row['interest']) }}</td>
                            <td class="number">{{ number_format($row['problem_count'], 0, ',', '.') }}</td>
                            <td class="number">{{ $formatRupiah($row['problem_principal']) }}</td>
                            <td class="number">{{ $formatRupiah($row['problem_interest']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="empty-row" colspan="8">Belum ada data outstanding untuk ditampilkan.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="number">{{ number_format($sectorTotal['mitra_count'], 0, ',', '.') }}</td>
                        <td class="number">{{ $formatRupiah($sectorTotal['principal']) }}</td>
                        <td class="number">{{ $formatRupiah($sectorTotal['interest']) }}</td>
                        <td class="number">{{ number_format($sectorTotal['problem_count'], 0, ',', '.') }}</td>
                        <td class="number">{{ $formatRupiah($sectorTotal['problem_principal']) }}</td>
                        <td class="number">{{ $formatRupiah($sectorTotal['problem_interest']) }}</td>
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
                        <th>Jumlah UMKM Binaan</th>
                        <th>Nilai Pokok Piutang (Rp)</th>
                        <th>Bunga (Rp)</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($qualityRows as $row)
                        <tr>
                            <td class="center">{{ $loop->iteration }}</td>
                            <td><span @class(['quality-badge', $qualityClasses[$row['label']]])>{{ $row['label'] }}</span></td>
                            <td class="number">{{ number_format($row['mitra_count'], 0, ',', '.') }}</td>
                            <td class="number">{{ $formatRupiah($row['principal']) }}</td>
                            <td class="number">{{ $formatRupiah($row['interest']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="number">{{ number_format($qualityTotal['mitra_count'], 0, ',', '.') }}</td>
                        <td class="number">{{ $formatRupiah($qualityTotal['principal']) }}</td>
                        <td class="number">{{ $formatRupiah($qualityTotal['interest']) }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        @if($unratedLoans > 0)
            <p class="outstanding-note">{{ number_format($unratedLoans, 0, ',', '.') }} pinjaman belum memiliki kualitas piutang yang dapat dinilai; saldo tetap disertakan pada rekap wilayah dan sektor.</p>
        @endif
    </div>
</x-layouts.pumk-admin>
