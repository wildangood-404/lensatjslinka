<x-layouts.pumk-admin title="Daftar UMKM Binaan Via BRI">
    @include('pumk-admin.partials.module-styles')

    <div class="pumk-page">
        <div class="pumk-page-header">
            <div>
                <h1 class="pumk-page-title">Daftar UMKM Binaan Via BRI</h1>
                <p class="pumk-page-subtitle">Data ditampilkan berdasarkan snapshot laporan bulanan yang dipilih.</p>
            </div>
            <div class="pumk-page-header-actions">
                <a href="{{ route('pumk-admin.umkm-binaan-bri.create') }}" class="pumk-primary-button">+ Tambah UMKM Binaan Via BRI</a>
            </div>
        </div>

        @if(session('success'))<div class="pumk-alert success" role="status">{{ session('success') }}</div>@endif
        @if(session('warning'))<div class="pumk-alert warning" role="status">{{ session('warning') }}</div>@endif

        <form method="GET" action="{{ route('pumk-admin.umkm-binaan-bri.index') }}" class="pumk-card pumk-filter" style="display:block;margin-bottom:20px;padding:16px">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="pumk-field">
                    <label for="q">Cari nama mitra atau BRI Unit</label>
                    <input id="q" name="q" class="pumk-input" value="{{ request('q') }}" maxlength="100" placeholder="Masukkan kata kunci">
                </div>
                <div class="pumk-field">
                    <label for="wilayah">Wilayah</label>
                    <input id="wilayah" name="wilayah" class="pumk-input" value="{{ request('wilayah') }}" maxlength="255" placeholder="Cari kabupaten atau provinsi">
                </div>
                <div class="pumk-field">
                    <label for="sektor">Sektor Usaha</label>
                    <select id="sektor" name="sektor" class="pumk-select">
                        <option value="">Semua sektor</option>
                        @foreach($sectorOptions as $sector)
                            <option value="{{ $sector }}" @selected(request('sektor') === $sector)>{{ $sector }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pumk-field">
                    <label for="kolektibilitas">Kolektabilitas</label>
                    <select id="kolektibilitas" name="kolektibilitas" class="pumk-select">
                        <option value="">Semua status</option>
                        @foreach(['lancar' => 'Lancar', 'kurang_lancar' => 'Kurang Lancar', 'diragukan' => 'Diragukan', 'macet' => 'Macet'] as $value => $label)
                            <option value="{{ $value }}" @selected($selectedCollectibility === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
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
                <button type="submit" class="pumk-primary-button">Cari</button>
                <a href="{{ route('pumk-admin.umkm-binaan-bri.index', ['bulan' => $selectedMonth, 'tahun' => $selectedYear]) }}" class="pumk-secondary-button">Reset</a>
            </div>
        </form>

        <section class="pumk-card">
            <p style="margin:0 0 12px;color:#64748b;font-size:12px">
                Periode laporan: <strong>{{ $months[$selectedMonth] }} {{ $selectedYear }}</strong>
            </p>
            <div class="pumk-table-wrap">
                <table class="pumk-table">
                    <thead>
                    <tr>
                        <th>Nama Mitra</th>
                        <th>BRI Unit</th>
                        <th>Wilayah</th>
                        <th>Sektor Usaha</th>
                        <th>Kolektabilitas</th>
                        <th style="text-align:right">Total Sisa</th>
                        <th>Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($mitraList as $mitra)
                        <tr>
                            <td><strong>{{ $mitra->report_name }}</strong></td>
                            <td>{{ $mitra->report_unit }}</td>
                            <td>{{ $mitra->report_region }}</td>
                            <td>{{ $mitra->report_sector }}</td>
                            <td>{{ $mitra->report_collectibility }}</td>
                            <td style="text-align:right;font-weight:600">Rp{{ number_format((float) $mitra->report_outstanding, 0, ',', '.') }}</td>
                            <td class="actions">
                                <a href="{{ route('pumk-admin.umkm-binaan-bri.snapshot.show', ['mitra' => $mitra->id, 'bulan' => $selectedMonth, 'tahun' => $selectedYear]) }}" class="pumk-table-action is-primary">Profil</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="pumk-empty">Belum ada data snapshot pada periode ini.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($mitraList->hasPages())
                <div class="pumk-pagination">{{ $mitraList->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.pumk-admin>
