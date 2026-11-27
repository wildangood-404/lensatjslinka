<x-layouts.pumk-admin :title="'Profil UMKM Binaan Via BRI - '.$mitra->nama_mitra">
    @include('pumk-admin.partials.module-styles')

    @php
        $formatAmount = static fn ($value) => $value === null ? '—' : 'Rp'.number_format((float) $value, 0, ',', '.');
        $formatDate = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d/m/Y') : '—';
        $fieldGroups = [
            'Identitas & Lokasi Mitra' => [
                'Kode Unit' => 'kode_unit',
                'BRI Unit' => 'bri_unit',
                'Nama Mitra Binaan' => 'nama_mitra_binaan',
                'Alamat' => 'alamat',
                'Provinsi' => 'provinsi',
                'Kabupaten/Kota' => 'kabupaten_kota',
                'Sektor Ekonomi BUMN' => 'sektor_ekonomi_bumn',
                'Jenis Usaha' => 'jenis_usaha',
            ],
            'Rincian Pinjaman & Kinerja Bulanan' => [
                'Jangka Waktu' => 'jangka_waktu',
                'Tanggal Realisasi' => 'tanggal_realisasi',
                'Tanggal Jatuh Tempo' => 'tanggal_jatuh_tempo',
                'Jumlah Realisasi' => 'jumlah_realisasi',
                'Outstanding' => 'outstanding',
                'Kolektabilitas BUMN' => 'kolektibilitas_bumn',
                'Tanggal Lunas' => 'tanggal_lunas',
                'Accrued Interest' => 'accrued_interest',
                'Angsuran Bunga yang Diterima Giro' => 'angsuran_bunga_diterima_giro',
            ],
        ];
    @endphp

    <div class="pumk-page">
        <div class="pumk-page-header">
            <div>
                <h1 class="pumk-page-title">Profil UMKM Binaan Via BRI</h1>
                <p class="pumk-page-subtitle">{{ $mitra->nama_mitra }} · Laporan {{ $months[$selectedMonth] }} {{ $selectedYear }}</p>
            </div>
            <div class="pumk-page-header-actions">
                <a href="{{ route('pumk-admin.umkm-binaan-bri.index', ['bulan' => $selectedMonth, 'tahun' => $selectedYear]) }}" class="pumk-secondary-button">Kembali ke daftar</a>
            </div>
        </div>

        @forelse($currentSnapshots as $snapshot)
            <section class="pumk-card" style="margin-bottom:18px;padding:20px">
                <h2 style="margin:0 0 14px;font-size:16px;font-weight:700">
                    Laporan {{ $months[$snapshot->bulan] }} {{ $snapshot->tahun }}
                    @if($currentSnapshots->count() > 1)<span style="color:#64748b;font-size:12px;font-weight:400">· Fasilitas {{ $loop->iteration }}</span>@endif
                </h2>
                @foreach($fieldGroups as $groupTitle => $fields)
                    <h3 style="margin:18px 0 10px;font-size:13px;font-weight:700;color:#334155">{{ $groupTitle }}</h3>
                    <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px 20px;margin:0">
                        @foreach($fields as $label => $attribute)
                            @php
                                $value = $snapshot->{$attribute};
                                if (in_array($attribute, ['tanggal_realisasi', 'tanggal_jatuh_tempo', 'tanggal_lunas'], true)) {
                                    $value = $formatDate($value);
                                } elseif (in_array($attribute, ['jumlah_realisasi', 'outstanding', 'accrued_interest', 'angsuran_bunga_diterima_giro'], true)) {
                                    $value = $formatAmount($value);
                                } else {
                                    $value = filled($value) ? $value : '—';
                                }
                            @endphp
                            <div>
                                <dt style="color:#64748b;font-size:11px">{{ $label }}</dt>
                                <dd style="margin:3px 0 0;color:#0f172a;font-size:13px;font-weight:600;overflow-wrap:anywhere">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endforeach
            </section>
        @empty
            <section class="pumk-card pumk-empty">Tidak ada snapshot mitra pada periode yang dipilih.</section>
        @endforelse

        @foreach($historyByFacility as $facilityId => $snapshots)
            <section class="pumk-card" style="margin-bottom:18px;padding:20px">
                <h2 style="margin:0 0 12px;font-size:16px;font-weight:700">Riwayat Perbandingan Bulanan</h2>
                <p style="margin:0 0 12px;color:#64748b;font-size:12px">
                    {{ $snapshots->first()->nama_mitra_binaan ?: $mitra->nama_mitra }}
                    · {{ $snapshots->first()->bri_unit ?: 'BRI Unit belum tersedia' }}
                </p>
                <div class="pumk-table-wrap">
                    <table class="pumk-table">
                        <thead>
                        <tr>
                            <th>Periode</th>
                            <th>Jumlah Realisasi</th>
                            <th>Outstanding</th>
                            <th>Kolektabilitas BUMN</th>
                            <th>Accrued Interest</th>
                            <th>Angsuran Bunga Diterima Giro</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($snapshots->sortByDesc(fn ($item) => ($item->tahun * 100) + $item->bulan) as $item)
                            <tr>
                                <td>{{ $months[$item->bulan] }} {{ $item->tahun }}</td>
                                <td>{{ $formatAmount($item->jumlah_realisasi) }}</td>
                                <td>{{ $formatAmount($item->outstanding ?? $item->saldo_piutang) }}</td>
                                <td>{{ $item->kolektibilitas_bumn ?? $item->kolektibilitas_label ?? '—' }}</td>
                                <td>{{ $formatAmount($item->accrued_interest) }}</td>
                                <td>{{ $formatAmount($item->angsuran_bunga_diterima_giro) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.pumk-admin>
