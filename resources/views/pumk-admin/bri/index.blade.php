<x-layouts.pumk-admin title="PUMK BRI">
    @include('pumk-admin.partials.module-styles')

    @php
        $rupiah = static function ($amount): string {
            $amount = (string) $amount;
            $negative = str_starts_with($amount, '-');
            $digits = ltrim($amount, '-');
            $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $digits) ?? $digits;

            return ($negative ? '-' : '').'Rp '.$grouped;
        };
        $percentageTone = static function ($percentage): string {
            if (bccomp((string) $percentage, '80', 2) >= 0) {
                return 'is-high';
            }

            return bccomp((string) $percentage, '50', 2) < 0 ? 'is-low' : 'is-medium';
        };
        $percentageWidth = static fn ($percentage): string => number_format(
            min(100, max(0, (float) $percentage)),
            2,
            '.',
            '',
        );
        $totalPercentage = bccomp((string) $totalBudget, '0', 0) > 0
            ? bcdiv(bcmul((string) $totalRealization, '100', 2), (string) $totalBudget, 2)
            : '0.00';
    @endphp

    <style>
        [x-cloak]{display:none!important}
        .pumk-bri-page{max-width:1320px}
        .pumk-bri-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:22px}
        .pumk-bri-heading h1{margin:0;color:#0f172a;font-size:32px;font-weight:700;letter-spacing:-.03em}
        .pumk-bri-heading p{margin:7px 0 0;color:#64748b;font-size:13px}
        .pumk-bri-table-card,.pumk-bri-panel{overflow:hidden;border:1px solid #e2e8f0;border-radius:16px;background:#fff;box-shadow:0 2px 6px rgba(15,23,42,.045)}
        .pumk-bri-table-wrap{overflow-x:auto}
        .pumk-bri-table{width:100%;min-width:1000px;border-collapse:collapse;font-size:12px}
        .pumk-bri-table th,.pumk-bri-table td{border:1px solid #e2e8f0;padding:11px 12px;vertical-align:middle}
        .pumk-bri-table thead th{background:#f1f5f9;color:#334155;text-align:center;font-size:11px;font-weight:700}
        .pumk-bri-table tbody td{color:#334155}
        .pumk-bri-table .center{text-align:center}
        .pumk-bri-table .amount{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
        .pumk-bri-period{min-width:78px;text-align:center;font-size:14px;font-weight:700;color:#0f172a}
        .pumk-bri-budget{min-width:175px;text-align:right;font-weight:700;white-space:nowrap}
        .pumk-bri-budget small{display:block;margin-top:5px;color:#64748b;font-size:10px;font-weight:500;line-height:1.55;white-space:normal}
        .pumk-bri-percentage{min-width:120px;text-align:center}
        .pumk-bri-percentage-badge{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:800;font-variant-numeric:tabular-nums;white-space:nowrap}
        .pumk-bri-percentage-badge.is-high{background:#dcfce7;color:#047857}
        .pumk-bri-percentage-badge.is-medium{background:#dbeafe;color:#1d4ed8}
        .pumk-bri-percentage-badge.is-low{background:#fef3c7;color:#b45309}
        .pumk-bri-progress-track{height:5px;width:100%;max-width:100px;margin:7px auto 0;overflow:hidden;border-radius:999px;background:#e2e8f0}
        .pumk-bri-progress-fill{display:block;height:100%;border-radius:inherit;background:#f59e0b}
        .pumk-bri-progress-fill.is-high{background:#10b981}
        .pumk-bri-progress-fill.is-medium{background:#3b82f6}
        .pumk-bri-progress-fill.is-low{background:#f59e0b}
        .pumk-bri-table tfoot td{border-top:2px solid #94a3b8;background:#f8fafc;color:#0f172a;font-weight:700}
        .pumk-bri-empty{padding:30px!important;color:#64748b!important;text-align:center}
        .pumk-bri-docs{display:flex;align-items:center;justify-content:center;gap:7px;min-width:105px}
        .pumk-bri-doc-indicator{display:inline-grid;width:28px;height:28px;place-items:center;border-radius:999px;font-size:15px;font-weight:800;text-decoration:none}
        .pumk-bri-doc-indicator.is-uploaded{background:#dcfce7;color:#059669}
        .pumk-bri-doc-indicator.is-missing{background:#fef3c7;color:#d97706}
        .pumk-bri-doc-download{color:#2563eb;font-size:10px;font-weight:700;text-decoration:none}
        .pumk-bri-panels{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;margin-top:22px}
        .pumk-bri-panel{padding:22px}
        .pumk-bri-panel h2{margin:0;color:#0f172a;font-size:17px;font-weight:700}
        .pumk-bri-panel>p{margin:5px 0 18px;color:#64748b;font-size:12px}
        .pumk-bri-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .pumk-bri-field{display:flex;min-width:0;flex-direction:column;gap:6px}
        .pumk-bri-field.is-wide{grid-column:1/-1}
        .pumk-bri-field label{color:#334155;font-size:11px;font-weight:600}
        .pumk-bri-field input,.pumk-bri-field select,.pumk-bri-field textarea{width:100%;min-width:0;min-height:41px;border:1px solid #cbd5e1;border-radius:9px;background:#f8fafc;padding:9px 11px;color:#0f172a;font:400 12px Poppins,sans-serif}
        .pumk-bri-field input[type="file"].sr-only{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
        .pumk-bri-field textarea{min-height:78px;resize:vertical}
        .pumk-bri-field input:focus,.pumk-bri-field select:focus,.pumk-bri-field textarea:focus{outline:2px solid rgba(37,99,235,.16);border-color:#3b82f6;background:#fff}
        .pumk-bri-file-picker{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:45px;border:1px dashed #94a3b8;border-radius:9px;background:#f8fafc;padding:8px 10px;color:#64748b;font-size:11px;cursor:pointer}
        .pumk-bri-file-picker:hover{border-color:#60a5fa;background:#eff6ff}
        .pumk-bri-file-picker strong{flex:none;border-radius:7px;background:#fff;padding:7px 10px;color:#2563eb;font-size:10px;box-shadow:0 1px 3px rgba(15,23,42,.12)}
        .pumk-bri-form-actions{display:flex;justify-content:flex-end;margin-top:17px}
        .pumk-bri-form-actions button:disabled{cursor:not-allowed;opacity:.55}
        .pumk-bri-error{color:#b91c1c;font-size:11px}
        .pumk-bri-note{margin-top:12px;color:#64748b;font-size:10px}
        @media(max-width:850px){.pumk-bri-panels{grid-template-columns:1fr}}
        @media(max-width:620px){.pumk-bri-heading h1{font-size:27px}.pumk-bri-panel{padding:17px}.pumk-bri-form-grid{grid-template-columns:1fr}.pumk-bri-field.is-wide{grid-column:auto}.pumk-bri-form-actions button{width:100%}}
        @media print{.pumk-header,.pumk-sidebar,.pumk-mobile-toggle,.pumk-overlay,.pumk-bri-panels{display:none!important}.pumk-main,.pumk-shell.is-collapsed .pumk-main{margin:0;padding:0}.pumk-shell{background:#fff}.pumk-page{max-width:none}.pumk-bri-table-card{box-shadow:none}}
    </style>

    <div class="pumk-page pumk-bri-page">
        <header class="pumk-bri-heading">
            <div>
                <h1>PUMK BRI</h1>
                <p>Rekapitulasi anggaran penyaluran dan rincian realisasi PUMK BRI per periode tahun.</p>
            </div>
        </header>

        @if(session('success'))
            <div class="pumk-alert success" role="status">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="pumk-alert error" role="alert">
                <strong>Periksa kembali isian:</strong>
                <ul style="margin:7px 0 0;padding-left:20px">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <section class="pumk-bri-table-card" aria-label="Rekapitulasi Anggaran dan Realisasi PUMK BRI">
            <div class="pumk-bri-table-wrap">
                <table class="pumk-bri-table">
                    <caption class="sr-only">Tabel Rekapitulasi Anggaran &amp; Realisasi PUMK BRI</caption>
                    <thead>
                    <tr>
                        <th rowspan="2">No.</th>
                        <th rowspan="2">Periode Tahun</th>
                        <th rowspan="2">Anggaran Penyaluran PUMK (RKA) (Rp.)</th>
                        <th rowspan="2">Persentase (%)</th>
                        <th colspan="5">Realisasi</th>
                        <th rowspan="2">Aksi</th>
                    </tr>
                    <tr>
                        <th>Uraian</th>
                        <th>Waktu</th>
                        <th>PIC</th>
                        <th>Nominal (Rp.)</th>
                        <th>Dokumen</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($periods as $period)
                        @php
                            $realizations = $period->realisasi;
                            $rowspan = max(1, $realizations->count());
                            $periodActual = $realizations->reduce(
                                fn (string $sum, $realization): string => bcadd($sum, (string) $realization->nominal, 0),
                                '0',
                            );
                            $budget = (string) $period->anggaran_rka;
                            $remaining = bcsub($budget, $periodActual, 0);
                            $periodPercentage = bccomp($budget, '0', 0) > 0
                                ? bcdiv(bcmul($periodActual, '100', 2), $budget, 2)
                                : '0.00';
                            $periodPercentageTone = $percentageTone($periodPercentage);
                        @endphp
                        @if($realizations->isEmpty())
                            <tr>
                                <td rowspan="{{ $rowspan }}" class="center">{{ $loop->iteration }}</td>
                                <td rowspan="{{ $rowspan }}" class="pumk-bri-period">{{ $period->periode_tahun }}</td>
                                <td rowspan="{{ $rowspan }}" class="pumk-bri-budget">
                                    {{ $rupiah($budget) }}
                                    <small>
                                        @if(bccomp($budget, '0', 0) > 0)
                                            Sisa {{ $rupiah($remaining) }}
                                        @else
                                            Belum ada anggaran untuk dihitung
                                        @endif
                                    </small>
                                </td>
                                <td rowspan="{{ $rowspan }}" class="pumk-bri-percentage">
                                    <span class="pumk-bri-percentage-badge {{ $periodPercentageTone }}">
                                        {{ number_format((float) $periodPercentage, 2, ',', '.') }}%
                                    </span>
                                    <div class="pumk-bri-progress-track" role="progressbar"
                                         aria-label="Persentase realisasi periode {{ $period->periode_tahun }}"
                                         aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percentageWidth($periodPercentage) }}">
                                        <span class="pumk-bri-progress-fill {{ $periodPercentageTone }}"
                                              style="width: {{ $percentageWidth($periodPercentage) }}%"></span>
                                    </div>
                                </td>
                                <td class="center">—</td>
                                <td class="center">—</td>
                                <td class="center">—</td>
                                <td class="amount">—</td>
                                <td class="center">
                                    <span class="pumk-bri-docs">
                                        <span class="pumk-bri-doc-indicator is-missing" title="Dokumen belum diunggah" aria-label="Dokumen belum diunggah">?</span>
                                    </span>
                                </td>
                                <td class="center">—</td>
                            </tr>
                        @else
                            @foreach($realizations as $realization)
                                <tr>
                                    @if($loop->first)
                                        <td rowspan="{{ $rowspan }}" class="center">{{ $periods->search($period) + 1 }}</td>
                                        <td rowspan="{{ $rowspan }}" class="pumk-bri-period">{{ $period->periode_tahun }}</td>
                                        <td rowspan="{{ $rowspan }}" class="pumk-bri-budget">
                                            {{ $rupiah($budget) }}
                                            <small>
                                                @if(bccomp($budget, '0', 0) > 0)
                                                    Sisa {{ $rupiah($remaining) }}
                                                @else
                                                    Belum ada anggaran untuk dihitung
                                                @endif
                                            </small>
                                        </td>
                                        <td rowspan="{{ $rowspan }}" class="pumk-bri-percentage">
                                            <span class="pumk-bri-percentage-badge {{ $periodPercentageTone }}">
                                                {{ number_format((float) $periodPercentage, 2, ',', '.') }}%
                                            </span>
                                            <div class="pumk-bri-progress-track" role="progressbar"
                                                 aria-label="Persentase realisasi periode {{ $period->periode_tahun }}"
                                                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percentageWidth($periodPercentage) }}">
                                                <span class="pumk-bri-progress-fill {{ $periodPercentageTone }}"
                                                      style="width: {{ $percentageWidth($periodPercentage) }}%"></span>
                                            </div>
                                        </td>
                                    @endif
                                    <td>{{ $realization->uraian }}</td>
                                    <td class="center whitespace-nowrap">{{ $realization->waktu->format('d/m/Y') }}</td>
                                    <td>{{ $realization->pic }}</td>
                                    <td class="amount">{{ $rupiah($realization->nominal) }}</td>
                                    <td class="center">
                                        <span class="pumk-bri-docs">
                                            @if(filled($realization->dokumen_path))
                                                <a href="{{ route('pumk-admin.umkm-binaan-bri.pumk-bri.documents.view', $realization) }}"
                                                   class="pumk-bri-doc-indicator is-uploaded" title="Lihat dokumen" aria-label="Lihat dokumen">✓</a>
                                                <a href="{{ route('pumk-admin.umkm-binaan-bri.pumk-bri.documents.download', $realization) }}"
                                                   class="pumk-bri-doc-download">Unduh</a>
                                            @else
                                                <span class="pumk-bri-doc-indicator is-missing" title="Dokumen belum diunggah" aria-label="Dokumen belum diunggah">?</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td class="center">—</td>
                                </tr>
                            @endforeach
                        @endif
                    @empty
                        <tr><td colspan="10" class="pumk-bri-empty">Belum ada periode PUMK BRI. Tambahkan periode tahun dan anggaran di bawah.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="amount">{{ $rupiah($totalBudget) }}</td>
                        <td class="pumk-bri-percentage">
                            <span class="pumk-bri-percentage-badge {{ $percentageTone($totalPercentage) }}">
                                {{ number_format((float) $totalPercentage, 2, ',', '.') }}%
                            </span>
                            <div class="pumk-bri-progress-track" role="progressbar"
                                 aria-label="Persentase realisasi keseluruhan"
                                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percentageWidth($totalPercentage) }}">
                                <span class="pumk-bri-progress-fill {{ $percentageTone($totalPercentage) }}"
                                      style="width: {{ $percentageWidth($totalPercentage) }}%"></span>
                            </div>
                        </td>
                        <td colspan="3"></td>
                        <td class="amount">{{ $rupiah($totalRealization) }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="pumk-bri-panels" aria-label="Pembaruan periode dan realisasi">
            <article class="pumk-bri-panel">
                <h2>Update Periode Tahun</h2>
                <p>Tambah periode baru atau perbarui nilai anggaran untuk tahun yang sudah terdaftar.</p>
                <form method="POST" action="{{ route('pumk-admin.umkm-binaan-bri.pumk-bri.period.store') }}">
                    @csrf
                    <div class="pumk-bri-form-grid">
                        <div class="pumk-bri-field">
                            <label for="periode_tahun">Periode Tahun</label>
                            <input id="periode_tahun" name="periode_tahun" type="number" min="1900" max="2100" step="1"
                                   value="{{ old('periode_tahun', $defaultYear) }}" required>
                            @error('periode_tahun')<span class="pumk-bri-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="pumk-bri-field">
                            <label for="anggaran_rka">Anggaran Penyaluran PUMK (RKA) (Rp.)</label>
                            <input id="anggaran_rka" name="anggaran_rka" type="text" inputmode="numeric" class="pumk-bri-money"
                                   value="{{ old('anggaran_rka') }}" placeholder="Contoh: 1.000.000.000" required>
                            @error('anggaran_rka')<span class="pumk-bri-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="pumk-bri-form-actions">
                        <button type="submit" class="pumk-primary-button">Simpan Periode Tahun</button>
                    </div>
                </form>
            </article>

            <article class="pumk-bri-panel">
                <h2>Update Realisasi</h2>
                <p>Tambahkan lebih dari satu rincian realisasi pada periode tahun yang sama.</p>
                <form method="POST" action="{{ route('pumk-admin.umkm-binaan-bri.pumk-bri.realization.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="pumk-bri-form-grid">
                        <div class="pumk-bri-field is-wide">
                            <label for="pumk_bri_period_id">Pilih Periode Tahun *</label>
                            <select id="pumk_bri_period_id" name="pumk_bri_period_id" required @disabled($periods->isEmpty())>
                                <option value="">Pilih periode tahun</option>
                                @foreach($periods as $period)
                                    <option value="{{ $period->id }}" @selected((string) old('pumk_bri_period_id') === (string) $period->id)>
                                        {{ $period->periode_tahun }}
                                    </option>
                                @endforeach
                            </select>
                            @error('pumk_bri_period_id')<span class="pumk-bri-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="pumk-bri-field is-wide">
                            <label for="uraian">Uraian *</label>
                            <textarea id="uraian" name="uraian" maxlength="3000" required>{{ old('uraian') }}</textarea>
                            @error('uraian')<span class="pumk-bri-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="pumk-bri-field">
                            <label for="waktu">Waktu *</label>
                            <input id="waktu" name="waktu" type="date" value="{{ old('waktu') }}" required>
                            @error('waktu')<span class="pumk-bri-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="pumk-bri-field">
                            <label for="pic">PIC *</label>
                            <input id="pic" name="pic" type="text" maxlength="255" value="{{ old('pic') }}" required>
                            @error('pic')<span class="pumk-bri-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="pumk-bri-field">
                            <label for="nominal">Nominal (Rp.) *</label>
                            <input id="nominal" name="nominal" type="text" inputmode="numeric" class="pumk-bri-money"
                                   value="{{ old('nominal') }}" placeholder="Contoh: 250.000.000" required>
                            @error('nominal')<span class="pumk-bri-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="pumk-bri-field">
                            <label for="dokumen">Dokumen (Opsional)</label>
                            <label for="dokumen" class="pumk-bri-file-picker">
                                <span data-bri-file-name>Pilih dokumen PDF, gambar, atau Office (maks. 10 MB)</span>
                                <strong>Pilih File</strong>
                                <input id="dokumen" name="dokumen" type="file" class="sr-only"
                                       accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx" data-bri-file-input>
                            </label>
                            @error('dokumen')<span class="pumk-bri-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <p class="pumk-bri-note">Dokumen dibatasi 10 MB per file. Jika tidak ada dokumen, tabel menampilkan tanda tanya.</p>
                    <div class="pumk-bri-form-actions">
                        <button type="submit" class="pumk-primary-button" @disabled($periods->isEmpty())>Tambah / Simpan Realisasi</button>
                    </div>
                </form>
            </article>
        </section>
    </div>

    <script>
        document.querySelectorAll('.pumk-bri-money').forEach(function (input) {
            input.addEventListener('input', function () {
                const digits = this.value.replace(/\D/g, '');
                this.value = digits ? digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
            });
        });
        const briFileInput = document.querySelector('[data-bri-file-input]');
        briFileInput?.addEventListener('change', function () {
            const name = this.files?.[0]?.name || 'Pilih dokumen PDF, gambar, atau Office (maks. 10 MB)';
            document.querySelector('[data-bri-file-name]').textContent = name;
        });
    </script>
</x-layouts.pumk-admin>
