<x-layouts.pumk-admin title="Tambah UMKM Binaan Via BRI">
    @include('pumk-admin.partials.module-styles')

    <div class="pumk-page">
        <div class="pumk-page-header">
            <div>
                <h1 class="pumk-page-title">Tambah UMKM Binaan Via BRI</h1>
                <p class="pumk-page-subtitle">Unggah satu laporan bulanan. Unggahan ulang pada periode yang sama mengganti snapshot periode tersebut saja.</p>
            </div>
            <div class="pumk-page-header-actions">
                <a href="{{ route('pumk-admin.umkm-binaan-bri.index') }}" class="pumk-secondary-button">Kembali ke daftar</a>
            </div>
        </div>

        @if($errors->any())
            <div class="pumk-alert error" role="alert">
                <strong>File belum dapat diproses:</strong>
                <ul style="margin:8px 0 0;padding-left:20px">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        @if(session('success'))<div class="pumk-alert success" role="status">{{ session('success') }}</div>@endif
        @if(session('warning'))<div class="pumk-alert warning" role="status">{{ session('warning') }}</div>@endif

        @if($result = session('import_result'))
            <section class="pumk-card" style="margin-bottom:20px;padding:18px">
                <h2 style="margin:0 0 10px;font-size:16px;font-weight:700">Hasil Upload</h2>
                <p style="margin:0">
                    {{ $months[$result['bulan']] ?? '—' }} {{ $result['tahun'] }} ·
                    {{ $result['baris'] }} baris · Total outstanding Rp{{ number_format((float) $result['total_saldo'], 0, ',', '.') }}.
                    {{ $result['pesan'] }}
                </p>
            </section>
        @endif

        <section class="pumk-card" style="padding:22px">
            <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px">
                <div>
                    <h2 style="margin:0;font-size:16px;font-weight:700">Upload Laporan Bulanan</h2>
                    <p style="margin:5px 0 0;color:#64748b;font-size:12px">Format .xlsx, .xls, atau .csv; ukuran maksimal 15 MB.</p>
                </div>
                <a href="{{ route('pumk-admin.umkm-binaan-bri.template') }}" class="pumk-secondary-button">Unduh Template Excel</a>
            </div>

            <form method="POST" action="{{ route('pumk-admin.umkm-binaan-bri.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="pumk-field">
                        <label for="bulan">Bulan Laporan</label>
                        <select id="bulan" name="bulan" class="pumk-select" required>
                            @foreach($months as $month => $label)
                                <option value="{{ $month }}" @selected($defaultMonth === $month)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('bulan')<span class="pumk-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="pumk-field">
                        <label for="tahun">Tahun Laporan</label>
                        <input id="tahun" name="tahun" type="number" min="1900" max="2100" step="1" value="{{ $defaultYear }}" class="pumk-input" required>
                        @error('tahun')<span class="pumk-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="pumk-field sm:col-span-2">
                        <label for="import_file">File Excel / CSV</label>
                        <input id="import_file" name="import_file" type="file" accept=".xlsx,.xls,.csv" class="pumk-input" required>
                        @error('import_file')<span class="pumk-field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:18px">
                    <button type="submit" class="pumk-primary-button">Upload dan Perbarui Snapshot</button>
                </div>
            </form>
        </section>

        <section class="pumk-card" style="margin-top:18px;padding:18px">
            <h2 style="margin:0 0 10px;font-size:15px;font-weight:700">Kolom Template (17 atribut)</h2>
            <ol style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:6px 24px;margin:0;padding-left:20px;color:#475569;font-size:12px">
                @foreach(\App\Services\Pumk\PumkBriMonthlySnapshotImportService::TEMPLATE_HEADERS as $header)
                    <li>{{ $header }}</li>
                @endforeach
            </ol>
        </section>
    </div>
</x-layouts.pumk-admin>
