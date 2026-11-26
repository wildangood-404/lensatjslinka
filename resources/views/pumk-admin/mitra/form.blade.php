<x-layouts.pumk-admin :title="$mitra->exists ? 'Edit UMKM Binaan' : 'Tambah UMKM Binaan'">
    @include('pumk-admin.partials.module-styles')

    @php
        $editing = $mitra->exists;
        $archiveEdit = $pinjaman->exists && ($pinjaman->status !== \App\Models\PumkPinjaman::STATUS_AKTIF || ! $pinjaman->is_active);
        $formAction = $editing ? route('pumk-admin.mitra.update', $mitra) : route('pumk-admin.mitra.store');
        $dateValue = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : '';
        $importActive = session()->has('import_result') || $errors->has('import_file');
        $domicileSame = filter_var(old('alamat_domisili_sama_ktp', $mitra->alamat_domisili_sama_ktp ?? true), FILTER_VALIDATE_BOOLEAN);
        $businessSame = filter_var(old('alamat_usaha_sama_ktp', $mitra->alamat_usaha_sama_ktp ?? true), FILTER_VALIDATE_BOOLEAN);
        $hasReschedule = collect([$pinjaman->reschedule_ke1, $pinjaman->reschedule_ke2, $pinjaman->reschedule_ke3, $pinjaman->reschedule_ke4])->filter()->isNotEmpty();
        $hasCollateral = collect([
            $pinjaman->jenis_jaminan, $pinjaman->jaminan_no_pol, $pinjaman->jaminan_no_bpkb,
            $pinjaman->jaminan_merk, $pinjaman->jaminan_type, $pinjaman->jaminan_tahun_kendaraan,
            $pinjaman->jaminan_no_sertifikat, $pinjaman->jaminan_luas, $pinjaman->jaminan_atas_nama, $pinjaman->jaminan_alamat,
        ])->filter()->isNotEmpty();
        $sectorOptions = collect([
            'Industri - Bengkel / Pertukangan',
            'Industri - Konveksi',
            'Industri - Kreatif',
            'Industri - Makanan Minuman',
            'Industri - Meubel',
            'Industri - Pengolahan',
            'Perdagangan',
            'Jasa',
            'Pertanian',
            'Peternakan',
            'Lainnya',
        ])->merge($sektorList->pluck('nama'))->unique()->sort()->values();
        $selectedSector = old('sektor_usaha', $mitra->sektor_sumber ?? $mitra->sektorUsaha?->nama);
        $supportingDocuments = $mitra->exists ? $mitra->dokumenPendukung()->latest('id')->get() : collect();
    @endphp

    <style>
        [x-cloak]{display:none!important}
        .umkm-modern-page{max-width:1280px;padding:18px;border-radius:24px;background:rgba(248,250,252,.72)}
        .umkm-modern-page .pumk-page-title{font-size:30px;letter-spacing:-.03em}
        .umkm-modern-page .pumk-page-subtitle{color:#64748b}
        .umkm-modern-card{margin-top:18px;padding:24px;border:1px solid rgba(226,232,240,.9);border-radius:18px;background:#fff;box-shadow:0 2px 6px rgba(15,23,42,.045)}
        .umkm-card-heading{display:flex;align-items:flex-start;gap:12px;margin-bottom:22px}
        .umkm-card-icon{display:grid;width:40px;height:40px;flex:none;place-items:center;border:1px solid #dbeafe;border-radius:12px;background:#eff6ff;color:#2563eb}
        .umkm-card-icon svg{width:20px;height:20px}
        .umkm-card-heading h2{margin:0;color:#0f172a;font-size:17px;font-weight:700;letter-spacing:-.01em}
        .umkm-card-heading p{margin:4px 0 0;color:#64748b;font-size:12px;line-height:1.55}
        .umkm-form-grid>.pumk-field,.umkm-modern-grid>.pumk-field{display:flex;min-width:0;flex-direction:column;align-items:stretch;gap:6px;padding:0}
        .umkm-form-grid>.pumk-field>label:not(.pumk-checkbox-row),.umkm-modern-grid>.pumk-field>label{padding:0!important;color:#334155;font-size:12px;font-weight:600}
        .umkm-modern-page .pumk-input,.umkm-modern-page .pumk-select,.umkm-modern-page .pumk-textarea{border:1px solid #e2e8f0;border-radius:9px;background:rgba(248,250,252,.8);color:#0f172a;font:400 13px Poppins,sans-serif}
        .umkm-modern-page .pumk-input,.umkm-modern-page .pumk-select{height:42px;padding:0 13px}
        .umkm-modern-page .pumk-textarea{min-height:84px;padding:11px 13px}
        .umkm-modern-page .pumk-input:focus,.umkm-modern-page .pumk-select:focus,.umkm-modern-page .pumk-textarea:focus{border-color:#3b82f6;background:#fff;box-shadow:0 0 0 3px rgba(59,130,246,.11)}
        .umkm-modern-page .pumk-note{margin-top:14px;border:0;border-radius:10px;background:#eff6ff;padding:11px 14px;color:#1d4ed8;font-size:12px}
        .umkm-input-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:5px;margin:0 0 18px;padding:6px;border:1px solid #e2e8f0;border-radius:13px;background:rgba(241,245,249,.9);box-shadow:none}
        .umkm-input-tabs button{display:flex;min-height:46px;align-items:center;justify-content:center;gap:9px;border:0;border-radius:9px;background:transparent;color:#64748b;font:500 13px Poppins,sans-serif;cursor:pointer}
        .umkm-input-tabs button.is-active{background:#fff;color:#2563eb;font-weight:600;box-shadow:0 1px 3px rgba(15,23,42,.12)}
        .umkm-input-tabs svg{width:17px;height:17px}
        .umkm-import-panel{padding:24px}.umkm-import-help{margin:8px 0 16px;color:#64748b;font-size:13px}
        .umkm-document-list{display:grid;gap:8px;margin:12px 0}.umkm-document-row{display:flex;justify-content:space-between;align-items:center;gap:12px;border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px}
        .umkm-document-row form{display:inline}.umkm-document-input{margin-top:8px}
        .umkm-import-errors{margin:10px 0 0;padding-left:22px;max-height:300px;overflow:auto}
        .umkm-address-box{border:1px solid #e2e8f0;border-radius:14px;background:#fff;padding:18px}
        .umkm-address-heading{display:flex;align-items:center;gap:9px;margin:0 0 16px;color:#334155;font-size:11px;font-weight:700;letter-spacing:.08em}
        .umkm-address-heading svg{width:17px;height:17px;color:#2563eb}
        .umkm-toggle-row{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid #dbeafe;border-radius:12px;background:#f8fbff;padding:13px}
        .umkm-toggle-row strong{display:block;color:#1e293b;font-size:12px;font-weight:600}.umkm-toggle-row span{display:block;margin-top:3px;color:#64748b;font-size:11px}
        .umkm-switch{position:relative;width:42px;height:24px;flex:none;appearance:none;border-radius:999px;background:#cbd5e1;cursor:pointer;transition:background .18s}
        .umkm-switch:checked{background:#2563eb}.umkm-switch::after{position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;content:'';transition:transform .18s;box-shadow:0 1px 3px rgba(15,23,42,.2)}
        .umkm-switch:checked::after{transform:translateX(18px)}
        .umkm-address-preview{margin-top:14px;border:1px dashed #cbd5e1;border-radius:11px;background:#f8fafc;padding:14px;color:#475569;font-size:12px;line-height:1.6}
        .umkm-address-preview strong{display:block;margin-bottom:4px;color:#334155;font-size:11px}
        .umkm-photo-drop{position:relative;display:flex;aspect-ratio:3/4;max-width:210px;flex-direction:column;align-items:center;justify-content:center;overflow:hidden;border:2px dashed #7dd3fc;border-radius:14px;background:rgba(240,249,255,.55);padding:16px;text-align:center;cursor:pointer;transition:background .18s,border-color .18s}
        .umkm-photo-drop:hover{border-color:#38bdf8;background:#f0f9ff}
        .umkm-photo-drop svg{width:38px;height:38px;color:#38bdf8}
        .umkm-photo-drop strong{margin-top:12px;color:#334155;font-size:12px;line-height:1.5}.umkm-photo-drop small{margin-top:5px;color:#64748b;font-size:10px}
        .umkm-photo-preview{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
        .umkm-subsection-title{margin:0;color:#334155;font-size:13px;font-weight:700}
        .umkm-subsection-copy{margin:4px 0 14px;color:#64748b;font-size:11px}
        .umkm-highlight-box{border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(135deg,#f8fbff,#eff6ff);padding:18px}
        .umkm-file-picker{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc;padding:12px;color:#475569;font-size:12px}
        .umkm-file-picker:hover{border-color:#60a5fa;background:#f0f9ff}
        .umkm-file-picker .pick-button{flex:none;border-radius:8px;background:#fff;padding:7px 11px;color:#2563eb;font-size:11px;font-weight:600;box-shadow:0 1px 2px rgba(15,23,42,.1)}
        .umkm-sticky-actions{position:sticky;bottom:14px;z-index:15;display:flex;justify-content:flex-end;gap:10px;margin-top:20px;border:1px solid rgba(226,232,240,.9);border-radius:14px;background:rgba(255,255,255,.94);padding:12px;box-shadow:0 8px 25px rgba(15,23,42,.1);backdrop-filter:blur(10px)}
        .umkm-sticky-actions .pumk-secondary-button{background:#f1f5f9;color:#475569}
        .umkm-modern-page .pumk-form-actions{margin-top:20px}
        .umkm-modern-page details>summary{list-style:none}.umkm-modern-page details>summary::-webkit-details-marker{display:none}
        .umkm-modern-page details[open] .umkm-accordion-chevron{transform:rotate(180deg)}
        .umkm-accordion-chevron{width:18px;height:18px;transition:transform .18s}
        .umkm-modern-profile{max-width:1280px;padding:18px;border-radius:24px;background:rgba(248,250,252,.72)}
        .umkm-profile-card{margin-top:18px;padding:24px;border:1px solid rgba(226,232,240,.9);border-radius:18px;background:#fff;box-shadow:0 2px 6px rgba(15,23,42,.045)}
        @media(max-width:760px){.umkm-modern-page{padding:12px;border-radius:16px}.umkm-modern-card{padding:18px}.umkm-document-row{align-items:flex-start;flex-direction:column}.umkm-sticky-actions{bottom:8px}.umkm-modern-page .pumk-page-header{gap:12px}}
        @media(max-width:480px){.umkm-input-tabs button{gap:5px;padding:8px 4px;font-size:10px}.umkm-input-tabs button svg{width:14px;height:14px}}
    </style>

    <div class="pumk-page umkm-modern-page bg-slate-50/70 p-4 md:p-8"
         x-data="{ activeTab: @js($importActive ? 'import' : 'manual'), domisiliSame: @js($domicileSame), usahaSame: @js($businessSame) }">
        <div class="pumk-page-header">
            <div>
                <h1 class="pumk-page-title">{{ $archiveEdit ? 'Edit Arsip dan Dokumen' : ($editing ? 'Edit UMKM Binaan' : 'Tambah UMKM Binaan') }}</h1>
                <p class="pumk-page-subtitle">Data tersimpan langsung tanpa alur approval. Kolom bertanda * wajib diisi.</p>
            </div>
        </div>

        @if($errors->any())
            <div class="pumk-alert error" role="alert">
                <strong>Periksa kembali isian:</strong>
                <ul style="margin:8px 0 0;padding-left:20px">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        @unless($editing)
            <div class="umkm-input-tabs" role="tablist" aria-label="Metode input UMKM Binaan">
                <button type="button" :class="{ 'is-active': activeTab === 'manual' }" role="tab" :aria-selected="activeTab === 'manual'" aria-controls="manual-panel" @click="activeTab = 'manual'">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5"/></svg>
                    Input Manual
                </button>
                <button type="button" :class="{ 'is-active': activeTab === 'import' }" role="tab" :aria-selected="activeTab === 'import'" aria-controls="import-panel" @click="activeTab = 'import'">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4.75h16v14.5H4zM8 9h8M8 12.5h8M8 16h4"/></svg>
                    Import Excel (Input Massal)
                </button>
            </div>
        @endunless

        <section id="manual-panel" x-show="activeTab === 'manual'" x-cloak data-input-panel="manual">
        <form id="pumk-mitra-form" method="POST" action="{{ $formAction }}" autocomplete="off" enctype="multipart/form-data">
            @csrf
            @if($editing) @method('PUT') @endif
            @if(request()->boolean('return_to_profile'))
                <input type="hidden" name="return_to_profile" value="1">
            @endif
            <input type="hidden" name="financial_snapshot" value="1">
            @if($pinjaman->exists)<input type="hidden" name="pinjaman_id" value="{{ $pinjaman->id }}">@endif
            <section class="pumk-card pumk-section umkm-modern-card">
                <div class="umkm-card-heading">
                    <span class="umkm-card-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.12a7.5 7.5 0 0 1 15 0"/></svg></span>
                    <div><h2>Identitas Utama &amp; Biodata Pemilik</h2><p>Lengkapi identitas pemilik, kontak aktif, dan informasi rekening.</p></div>
                </div>
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <div class="lg:col-span-3">
                        <label for="pasfoto" class="umkm-photo-drop">
                            <input id="pasfoto" type="file" name="pasfoto" class="sr-only" accept=".jpg,.jpeg,.png" data-photo-input>
                            <span data-photo-placeholder @if($mitra->pasfoto) hidden @endif class="flex flex-col items-center">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.12a7.5 7.5 0 0 1 15 0"/></svg>
                                <strong>Unggah Pasfoto</strong><small>JPG/PNG, maks. 5 MB</small>
                            </span>
                            <img data-photo-preview @if($mitra->pasfoto) src="{{ route('pumk-admin.umkm-binaan.photo', $mitra) }}" @else hidden @endif alt="Pratinjau pasfoto" class="umkm-photo-preview">
                        </label>
                        @error('pasfoto')<span class="pumk-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:col-span-9">
                        <div class="pumk-field">
                            <label for="nik">NIK (16 digit)</label>
                            <input id="nik" name="nik" class="pumk-input" value="{{ old('nik', $mitra->nik ?? $mitra->no_ktp_encrypted) }}" maxlength="16" minlength="16" inputmode="numeric">
                            @error('nik')<span class="pumk-field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="pumk-field">
                            <label for="nama_pemilik">Nama Lengkap</label>
                            <input id="nama_pemilik" name="nama_pemilik" class="pumk-input" value="{{ old('nama_pemilik', $mitra->nama_pemilik) }}" maxlength="255">
                        </div>
                        <div class="pumk-field">
                            <label for="jenis_kelamin">Jenis Kelamin</label>
                            <select id="jenis_kelamin" name="jenis_kelamin" class="pumk-select">
                                <option value="">Pilih jenis kelamin</option>
                                <option value="Laki-laki" @selected(old('jenis_kelamin', $mitra->jenis_kelamin) === 'Laki-laki')>Laki-laki</option>
                                <option value="Perempuan" @selected(old('jenis_kelamin', $mitra->jenis_kelamin) === 'Perempuan')>Perempuan</option>
                            </select>
                        </div>
                        <div class="pumk-field">
                            <label for="tempat_lahir">Tempat Lahir</label>
                            <input id="tempat_lahir" name="tempat_lahir" class="pumk-input" value="{{ old('tempat_lahir', $mitra->tempat_lahir) }}" maxlength="150">
                        </div>
                        <div class="pumk-field">
                            <label for="tanggal_lahir">Tanggal Lahir</label>
                            <input id="tanggal_lahir" type="date" name="tanggal_lahir" class="pumk-input" value="{{ old('tanggal_lahir', $dateValue($mitra->tanggal_lahir)) }}">
                        </div>
                        <div class="pumk-field">
                            <label for="no_telepon">No. Ponsel / WhatsApp</label>
                            <input id="no_telepon" name="no_telepon" type="tel" class="pumk-input" value="{{ old('no_telepon', $mitra->no_telepon_encrypted) }}" maxlength="64" inputmode="tel">
                        </div>
                        <div class="pumk-field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" class="pumk-input" value="{{ old('email', $mitra->email) }}" maxlength="255">
                        </div>
                        <div class="pumk-field">
                            <label for="no_rekening">Nomor Rekening</label>
                            <input id="no_rekening" name="no_rekening" class="pumk-input" value="{{ old('no_rekening', $mitra->no_rekening_encrypted) }}" maxlength="64" inputmode="numeric" autocomplete="off">
                        </div>
                    </div>
                </div>
                <p class="pumk-note">NIK dan nomor kontak disimpan terenkripsi untuk keamanan data mitra.</p>
            </section>

            <section class="pumk-card pumk-section umkm-modern-card">
                <div class="umkm-card-heading">
                    <span class="umkm-card-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 4l9 6.5M5.5 9.5V20h13V9.5M9 20v-6h6v6"/></svg></span>
                    <div><h2>Alamat KTP &amp; Domisili</h2><p>Gunakan alamat sesuai dokumen identitas atau aktifkan isian terpisah untuk domisili.</p></div>
                </div>
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <div class="umkm-address-box">
                        <h3 class="umkm-address-heading"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.5h16v13H4zM8 9h8M8 13h5"/></svg> DATA ALAMAT KTP</h3>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            @foreach(['provinsi' => 'Provinsi', 'kabupaten_kota' => 'Kab/Kota (Wilayah)', 'kecamatan' => 'Kecamatan', 'desa' => 'Desa / Kelurahan', 'rt' => 'RT', 'rw' => 'RW'] as $field => $label)
                                <div class="pumk-field">
                                    <label for="alamat_ktp_{{ $field }}">{{ $label }}</label>
                                    <input id="alamat_ktp_{{ $field }}" name="alamat_ktp_{{ $field }}" class="pumk-input" data-address-field="ktp.{{ $field }}" value="{{ old('alamat_ktp_'.$field, $mitra->{'alamat_ktp_'.$field} ?: ($field === 'provinsi' ? $mitra->provinsi : ($field === 'kabupaten_kota' ? ($mitra->kabupaten_kota ?: $mitra->wilayah?->nama) : null))) }}" maxlength="255">
                                </div>
                            @endforeach
                            <div class="pumk-field md:col-span-2">
                                <label for="alamat_ktp_lengkap">Alamat Lengkap</label>
                                <textarea id="alamat_ktp_lengkap" name="alamat_ktp_lengkap" class="pumk-textarea" data-address-field="ktp.lengkap" maxlength="3000">{{ old('alamat_ktp_lengkap', $mitra->alamat_ktp_lengkap ?? $mitra->alamat) }}</textarea>
                                <button type="button" class="pumk-secondary-button self-start" data-compose-address="ktp">Susun Otomatis</button>
                            </div>
                            <div class="pumk-field md:col-span-2">
                                <label for="alamat_ktp_share_lok">Share Lokasi (Google Maps / Koordinat)</label>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <input id="alamat_ktp_share_lok" name="alamat_ktp_share_lok" type="text" class="pumk-input min-w-0 flex-1" data-address-field="ktp.share_lok" value="{{ old('alamat_ktp_share_lok', $mitra->alamat_ktp_share_lok) }}" placeholder="https://maps.google.com/... atau -6.2, 106.8">
                                    <button type="button" class="pumk-secondary-button shrink-0" data-share-location="ktp">Ambil GPS</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="umkm-address-box">
                        <h3 class="umkm-address-heading"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.2"/></svg> ALAMAT DOMISILI SAAT INI</h3>
                        <label class="umkm-toggle-row">
                            <span><strong>Alamat domisili sama dengan alamat KTP</strong><span>Nonaktifkan jika alamat saat ini berbeda.</span></span>
                            <input type="hidden" name="alamat_domisili_sama_ktp" value="0">
                            <input id="alamat_domisili_sama_ktp" type="checkbox" name="alamat_domisili_sama_ktp" value="1" class="umkm-switch" data-address-toggle="domisili" x-model="domisiliSame">
                        </label>
                        <div class="umkm-address-preview" x-show="domisiliSame" data-address-summary-card="domisili">
                            <strong>Alamat domisili mengikuti alamat KTP</strong>
                            <span data-address-summary="domisili">Isi alamat KTP untuk melihat pratinjau.</span>
                        </div>
                        <div class="mt-4" data-address-panel="domisili" x-show="!domisiliSame" x-cloak>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                @foreach(['provinsi' => 'Provinsi', 'kabupaten_kota' => 'Kab/Kota (Wilayah)', 'kecamatan' => 'Kecamatan', 'desa' => 'Desa', 'rt' => 'RT', 'rw' => 'RW'] as $field => $label)
                                    <div class="pumk-field">
                                        <label for="alamat_domisili_{{ $field }}">{{ $label }}</label>
                                        <input id="alamat_domisili_{{ $field }}" name="alamat_domisili_{{ $field }}" class="pumk-input" data-address-field="domisili.{{ $field }}" value="{{ old('alamat_domisili_'.$field, $mitra->{'alamat_domisili_'.$field}) }}" maxlength="255">
                                    </div>
                                @endforeach
                                <div class="pumk-field md:col-span-2">
                                    <label for="alamat_domisili_lengkap">Alamat Lengkap</label>
                                    <textarea id="alamat_domisili_lengkap" name="alamat_domisili_lengkap" class="pumk-textarea" data-address-field="domisili.lengkap" maxlength="3000">{{ old('alamat_domisili_lengkap', $mitra->alamat_domisili_lengkap) }}</textarea>
                                    <button type="button" class="pumk-secondary-button self-start" data-compose-address="domisili">Susun Otomatis</button>
                                </div>
                                <div class="pumk-field md:col-span-2">
                                    <label for="alamat_domisili_share_lok">Share Lokasi</label>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <input id="alamat_domisili_share_lok" name="alamat_domisili_share_lok" type="text" class="pumk-input min-w-0 flex-1" data-address-field="domisili.share_lok" value="{{ old('alamat_domisili_share_lok', $mitra->alamat_domisili_share_lok) }}">
                                        <button type="button" class="pumk-secondary-button shrink-0" data-share-location="domisili">Ambil GPS</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="pumk-card pumk-section umkm-modern-card">
                <div class="umkm-card-heading">
                    <span class="umkm-card-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 20h18M5 20V8l7-4 7 4v12M9 11h.01M15 11h.01M9 15h.01M15 15h.01"/></svg></span>
                    <div><h2>Identitas Usaha &amp; Lokasi Usaha</h2><p>Informasi usaha binaan serta lokasi operasional yang dapat berbeda dari alamat KTP.</p></div>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="pumk-field">
                        <label for="nama_mitra">Nama Usaha / UMKM Binaan *</label>
                        <input id="nama_mitra" name="nama_mitra" class="pumk-input" value="{{ old('nama_mitra', $mitra->nama_mitra) }}" maxlength="255" required>
                        @error('nama_mitra')<span class="pumk-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="pumk-field">
                        <label for="sektor_usaha">Sektor Usaha</label>
                        <select id="sektor_usaha" name="sektor_usaha" class="pumk-select">
                            <option value="">Pilih sektor usaha</option>
                            @foreach($sectorOptions as $sectorOption)
                                <option value="{{ $sectorOption }}" @selected($selectedSector === $sectorOption)>{{ $sectorOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pumk-field">
                        <label for="jenis_usaha">Jenis Usaha</label>
                        <input id="jenis_usaha" name="jenis_usaha" class="pumk-input" value="{{ old('jenis_usaha', $mitra->jenis_usaha) }}" maxlength="255">
                    </div>
                </div>

                <div class="mt-6 border-t border-slate-100 pt-5">
                    <h3 class="umkm-subsection-title">Alamat Usaha</h3>
                    <p class="umkm-subsection-copy">Tentukan lokasi usaha atau salin otomatis dari alamat KTP.</p>
                    <label class="umkm-toggle-row">
                        <span><strong>Alamat usaha sama dengan alamat KTP</strong><span>Nonaktifkan untuk mengisi alamat usaha secara terpisah.</span></span>
                        <input type="hidden" name="alamat_usaha_sama_ktp" value="0">
                        <input id="alamat_usaha_sama_ktp" type="checkbox" name="alamat_usaha_sama_ktp" value="1" class="umkm-switch" data-address-toggle="usaha" x-model="usahaSame">
                    </label>
                    <div class="umkm-address-preview" x-show="usahaSame" data-address-summary-card="usaha">
                        <strong>Alamat usaha mengikuti alamat KTP</strong>
                        <span data-address-summary="usaha">Isi alamat KTP untuk melihat pratinjau.</span>
                    </div>
                    <div class="mt-4" data-address-panel="usaha" x-show="!usahaSame" x-cloak>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                            @foreach(['provinsi' => 'Provinsi', 'kabupaten_kota' => 'Kab/Kota (Wilayah)', 'kecamatan' => 'Kecamatan', 'desa' => 'Desa / Kelurahan', 'rt' => 'RT', 'rw' => 'RW'] as $field => $label)
                                <div class="pumk-field">
                                    <label for="alamat_usaha_{{ $field }}">{{ $label }}</label>
                                    <input id="alamat_usaha_{{ $field }}" name="alamat_usaha_{{ $field }}" class="pumk-input" data-address-field="usaha.{{ $field }}" value="{{ old('alamat_usaha_'.$field, $mitra->{'alamat_usaha_'.$field}) }}" maxlength="255">
                                </div>
                            @endforeach
                            <div class="pumk-field lg:col-span-2">
                                <label for="alamat_usaha_lengkap">Alamat Lengkap Usaha</label>
                                <textarea id="alamat_usaha_lengkap" name="alamat_usaha_lengkap" class="pumk-textarea" data-address-field="usaha.lengkap" maxlength="3000">{{ old('alamat_usaha_lengkap', $mitra->alamat_usaha_lengkap) }}</textarea>
                                <button type="button" class="pumk-secondary-button self-start" data-compose-address="usaha">Susun Otomatis</button>
                            </div>
                            <div class="pumk-field">
                                <label for="alamat_usaha_share_lok">Share Lokasi Usaha</label>
                                <div class="flex flex-col gap-2">
                                    <input id="alamat_usaha_share_lok" name="alamat_usaha_share_lok" type="text" class="pumk-input" data-address-field="usaha.share_lok" value="{{ old('alamat_usaha_share_lok', $mitra->alamat_usaha_share_lok) }}">
                                    <button type="button" class="pumk-secondary-button self-start" data-share-location="usaha">Ambil GPS</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="pumk-card pumk-section umkm-modern-card">
                <div class="umkm-card-heading">
                    <span class="umkm-card-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 7.5h17v12h-17zM7 7.5V5h10v2.5M7 12h4m-4 3.5h7"/></svg></span>
                    <div><h2>Data Pinjaman &amp; Kolektibilitas</h2><p>Atur kontrak, jadwal angsuran, nilai pinjaman, serta snapshot saldo terkini.</p></div>
                </div>
                @if($archiveEdit)
                    <div class="pumk-alert success">Status lunas/arsip dan saldo tetap. Anda dapat memperbarui identitas, nomor kontrak, jaminan, dan dokumen SPJ. Koreksi transaksi keuangan memerlukan tindakan Buka Kembali Pinjaman.</div>
                    <div class="pumk-field mb-5">
                        <label for="edit_reason">Alasan perubahan arsip *</label>
                        <textarea id="edit_reason" name="edit_reason" class="pumk-textarea" required minlength="5" maxlength="1000">{{ old('edit_reason') }}</textarea>
                    </div>
                @endif

                <div>
                    <h3 class="umkm-subsection-title">Nomor Kontrak &amp; Jadwal</h3>
                    <p class="umkm-subsection-copy">Data kontrak utama dan periode angsuran.</p>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div class="pumk-field">
                            <label for="spj_awal">Nomor SPJ Awal</label>
                            <input id="spj_awal" name="spj_awal" class="pumk-input" value="{{ old('spj_awal', $pinjaman->spj_awal) }}" maxlength="255">
                        </div>
                        <div class="pumk-field">
                            <label for="tanggal_pencairan">Tanggal Pencairan</label>
                            <input id="tanggal_pencairan" type="date" name="tanggal_pencairan" @disabled($archiveEdit) class="pumk-input" value="{{ old('tanggal_pencairan', $dateValue($pinjaman->tanggal_pencairan)) }}">
                        </div>
                        <div class="pumk-field">
                            <label for="tahun_pencairan">Tahun</label>
                            <input id="tahun_pencairan" type="number" name="tahun_pencairan" min="1900" max="2200" step="1" @disabled($archiveEdit) class="pumk-input" value="{{ old('tahun_pencairan', $pinjaman->tahun_pencairan) }}">
                        </div>
                        <div class="pumk-field">
                            <label for="mulai_angsuran">Mulai Angsuran</label>
                            <input id="mulai_angsuran" type="date" name="mulai_angsuran" @disabled($archiveEdit) class="pumk-input" value="{{ old('mulai_angsuran', $dateValue($pinjaman->mulai_angsuran)) }}">
                        </div>
                        <div class="pumk-field">
                            <label for="selesai_angsuran">Selesai Angsuran</label>
                            <input id="selesai_angsuran" type="date" name="selesai_angsuran" @disabled($archiveEdit) class="pumk-input" value="{{ old('selesai_angsuran', $dateValue($pinjaman->selesai_angsuran)) }}">
                        </div>
                    </div>
                    <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4" @if($hasReschedule) open @endif>
                        <summary class="flex cursor-pointer items-center justify-between gap-3 text-sm font-semibold text-slate-700">
                            <span>Nomor Kontrak Reschedule (Ke-1 s.d. Ke-4)</span>
                            <svg class="umkm-accordion-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            @foreach(range(1, 4) as $rescheduleNumber)
                                <div class="pumk-field">
                                    <label for="reschedule_ke{{ $rescheduleNumber }}">Kontrak Reschedule Ke-{{ $rescheduleNumber }}</label>
                                    <input id="reschedule_ke{{ $rescheduleNumber }}" name="reschedule_ke{{ $rescheduleNumber }}" class="pumk-input" value="{{ old('reschedule_ke'.$rescheduleNumber, $pinjaman->{'reschedule_ke'.$rescheduleNumber}) }}" maxlength="255">
                                </div>
                            @endforeach
                        </div>
                    </details>
                </div>

                <div class="umkm-highlight-box mt-6">
                    <h3 class="umkm-subsection-title">Nominal &amp; Sisa Pinjaman</h3>
                    <p class="umkm-subsection-copy">Total sisa dihitung dari pokok dan bunga, tetapi tetap dapat disesuaikan.</p>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div class="pumk-field">
                            <label for="pinjaman_pokok">Pencairan (Nilai Pokok - Rp)</label>
                            <input id="pinjaman_pokok" type="number" min="0" step="0.01" name="pinjaman_pokok" @disabled($archiveEdit) class="pumk-input" value="{{ old('pinjaman_pokok', $pinjaman->pinjaman_pokok) }}">
                        </div>
                        <div class="pumk-field">
                            <label for="persen_bunga">Persentase Bunga (%)</label>
                            <input id="persen_bunga" type="number" min="0" step="0.0001" name="persen_bunga" @disabled($archiveEdit) class="pumk-input" value="{{ old('persen_bunga', $pinjaman->persen_bunga) }}">
                        </div>
                        <div class="pumk-field">
                            <label for="pinjaman_bunga">Nilai Bunga (Rp)</label>
                            <input id="pinjaman_bunga" type="number" min="0" step="0.01" name="pinjaman_bunga" @disabled($archiveEdit) class="pumk-input" value="{{ old('pinjaman_bunga', $pinjaman->pinjaman_bunga) }}">
                        </div>
                        <div class="pumk-field">
                            <label for="nilai_angsuran_bulanan">Angsuran Bulanan (Rp)</label>
                            <input id="nilai_angsuran_bulanan" type="number" min="0" step="0.01" name="nilai_angsuran_bulanan" @disabled($archiveEdit) class="pumk-input" value="{{ old('nilai_angsuran_bulanan', $pinjaman->nilai_angsuran_bulanan) }}">
                        </div>
                        <div class="pumk-field">
                            <label for="sisa_pokok">Sisa Pinjaman Pokok (Rp)</label>
                            <input id="sisa_pokok" type="number" step="0.01" name="sisa_pokok" @disabled($archiveEdit) class="pumk-input" value="{{ old('sisa_pokok', $pinjaman->sisa_pokok) }}" data-snapshot-amount>
                        </div>
                        <div class="pumk-field">
                            <label for="sisa_bunga">Sisa Pinjaman Bunga (Rp)</label>
                            <input id="sisa_bunga" type="number" step="0.01" name="sisa_bunga" @disabled($archiveEdit) class="pumk-input" value="{{ old('sisa_bunga', $pinjaman->sisa_bunga) }}" data-snapshot-amount>
                        </div>
                        <div class="pumk-field">
                            <label for="total_sisa">Sisa Pinjaman Total (Pokok &amp; Bunga - otomatis)</label>
                            <input id="total_sisa" type="number" step="0.01" name="total_sisa" @disabled($archiveEdit) class="pumk-input" value="{{ old('total_sisa', $pinjaman->total_sisa) }}" data-snapshot-total>
                            <small class="text-[10px] leading-relaxed text-slate-500">Otomatis dijumlahkan; nominal dapat disesuaikan.</small>
                        </div>
                        <div class="pumk-field">
                            <label for="denda">Denda (Rp)</label>
                            <input id="denda" type="number" min="0" step="0.01" name="denda" @disabled($archiveEdit) class="pumk-input" value="{{ old('denda', $pinjaman->denda ?? '0.00') }}">
                        </div>
                        <div class="pumk-field">
                            <label for="kolektibilitas">Kolektibilitas</label>
                            <select id="kolektibilitas" name="kolektibilitas" class="pumk-select" @disabled($archiveEdit)>
                                <option value="">Pilih kolektibilitas</option>
                                @foreach(['lancar' => 'Lancar', 'kurang_lancar' => 'Kurang Lancar', 'diragukan' => 'Diragukan', 'macet' => 'Macet'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('kolektibilitas', $pinjaman->kolektibilitas) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pumk-field">
                            <label for="status_pinjaman">Status Pinjaman</label>
                            @php
                                $currentLoanStatus = $pinjaman->status_pinjaman ?: ($pinjaman->status === \App\Models\PumkPinjaman::STATUS_LUNAS ? 'lunas' : 'aktif');
                            @endphp
                            <select id="status_pinjaman" name="status_pinjaman" class="pumk-select" @disabled($archiveEdit)>
                                @foreach(['aktif' => 'Aktif', 'lunas' => 'Lunas', 'reschedule' => 'Reschedule', 'bermasalah' => 'Bermasalah'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status_pinjaman', $currentLoanStatus) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <p class="pumk-note">Jika jadwal angsuran belum tersedia, sisa pokok, bunga, total sisa, dan kolektibilitas disimpan sebagai snapshot. Status Lunas tetap mengikuti validasi pelunasan aplikasi.</p>
            </section>

            <section class="pumk-card pumk-section umkm-modern-card">
                <div class="umkm-card-heading">
                    <span class="umkm-card-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 13v6h14v-6"/></svg></span>
                    <div><h2>Dokumen Pendukung &amp; Jaminan</h2><p>Unggah berkas usaha dan kelengkapan kontrak. Informasi agunan bersifat opsional.</p></div>
                </div>
                <p class="umkm-subsection-copy">Dokumen pendukung dapat berupa KTP, dokumen pengajuan/perjanjian, atau foto usaha. Maksimal 10 MB per berkas.</p>
                @if($supportingDocuments->isNotEmpty())
                    <div class="umkm-document-list">
                        @foreach($supportingDocuments as $supportingDocument)
                            <div class="umkm-document-row">
                                <span class="text-sm font-medium text-slate-700">{{ $supportingDocument->nama_file_asli }}</span>
                                <span class="flex items-center gap-3">
                                    <a class="pumk-table-action" href="{{ route('pumk-admin.umkm-binaan.documents.download', [$mitra, $supportingDocument]) }}">Unduh</a>
                                    <form method="POST" action="{{ route('pumk-admin.umkm-binaan.documents.destroy', [$mitra, $supportingDocument]) }}" onsubmit="return confirm('Hapus dokumen ini?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="pumk-table-action">Hapus</button>
                                    </form>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div data-document-inputs>
                    <div class="pumk-field umkm-document-input max-w-2xl">
                        <label for="dokumen_pendukung_0">Dokumen Pendukung</label>
                        <label class="umkm-file-picker" for="dokumen_pendukung_0">
                            <span data-file-name>Belum ada berkas dipilih</span><span class="pick-button">Pilih Berkas</span>
                        </label>
                        <input id="dokumen_pendukung_0" type="file" name="dokumen_pendukung[]" class="sr-only" data-file-input>
                    </div>
                </div>
                <button type="button" class="pumk-secondary-button mt-3" data-add-document>+ Tambah Dokumen</button>
                @error('dokumen_pendukung.*')<span class="pumk-field-error">{{ $message }}</span>@enderror
                <div class="mt-6 border-t border-slate-100 pt-5">
                    <h3 class="umkm-subsection-title">Dokumen SPJ &amp; Reschedule</h3>
                    <p class="umkm-subsection-copy">Upload akan aktif setelah nomor kontrak terkait diisi.</p>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach($documentTypes as $type => $label)
                        @php
                            $contractField = \App\Models\PumkPinjamanDokumen::CONTRACT_FIELDS[$type];
                            $contractNumber = old($contractField, $pinjaman->getAttribute($contractField));
                            $document = $contractDocuments->get($type);
                        @endphp
                        <div class="pumk-field" data-contract-document data-contract-input="{{ $contractField }}">
                            <label for="dokumen_{{ $type }}">{{ $label }}</label>
                            <label class="umkm-file-picker" for="dokumen_{{ $type }}">
                                <span data-file-name>{{ $document ? 'Tersimpan: '.$document->nama_file_asli : 'Belum ada berkas dipilih' }}</span><span class="pick-button">Pilih Berkas</span>
                            </label>
                            <input id="dokumen_{{ $type }}" type="file" name="dokumen_{{ $type }}" class="sr-only" accept=".pdf,.jpg,.jpeg,.png" data-file-input data-existing-document="{{ $document ? '1' : '0' }}" @disabled(blank($contractNumber))>
                            <small class="text-[10px] leading-relaxed text-slate-500" data-document-help>
                                @if(blank($contractNumber))
                                    Isi nomor kontrak terlebih dahulu untuk mengaktifkan upload.
                                @elseif($document)
                                    Tersimpan: {{ $document->nama_file_asli }}
                                @else
                                    Belum ada dokumen.
                                @endif
                            </small>
                            @error("dokumen_{$type}")<span class="pumk-field-error">{{ $message }}</span>@enderror
                        </div>
                    @endforeach
                </div>
                </div>

                <details class="mt-6 rounded-xl border border-slate-200 bg-slate-50/60 p-4" @if($hasCollateral) open @endif>
                    <summary class="flex cursor-pointer items-center justify-between gap-3 text-sm font-semibold text-slate-700">
                        <span>+ Tambahkan Informasi Jaminan / Agunan (Opsional)</span>
                        <svg class="umkm-accordion-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div class="pumk-field md:col-span-2 lg:col-span-3">
                            <label for="jenis_jaminan">Jenis Jaminan</label>
                            <textarea id="jenis_jaminan" name="jenis_jaminan" class="pumk-textarea" maxlength="3000">{{ old('jenis_jaminan', $pinjaman->jenis_jaminan) }}</textarea>
                        </div>
                        <div class="pumk-field">
                            <label for="jaminan_no_pol">Nomor Polisi</label>
                            <input id="jaminan_no_pol" name="jaminan_no_pol" class="pumk-input" value="{{ old('jaminan_no_pol', $pinjaman->jaminan_no_pol) }}" maxlength="255">
                        </div>
                        <div class="pumk-field">
                            <label for="jaminan_no_bpkb">Nomor BPKB</label>
                            <input id="jaminan_no_bpkb" name="jaminan_no_bpkb" class="pumk-input" value="{{ old('jaminan_no_bpkb', $pinjaman->jaminan_no_bpkb) }}" maxlength="255">
                        </div>
                        <div class="pumk-field">
                            <label for="jaminan_merk">Jenis / Merek Kendaraan</label>
                            <input id="jaminan_merk" name="jaminan_merk" class="pumk-input" value="{{ old('jaminan_merk', $pinjaman->jaminan_merk) }}" maxlength="255">
                        </div>
                        <div class="pumk-field">
                            <label for="jaminan_type">Tipe Kendaraan</label>
                            <input id="jaminan_type" name="jaminan_type" class="pumk-input" value="{{ old('jaminan_type', $pinjaman->jaminan_type) }}" maxlength="255">
                        </div>
                        <div class="pumk-field">
                            <label for="jaminan_tahun_kendaraan">Tahun Kendaraan</label>
                            <input id="jaminan_tahun_kendaraan" name="jaminan_tahun_kendaraan" class="pumk-input" value="{{ old('jaminan_tahun_kendaraan', $pinjaman->jaminan_tahun_kendaraan) }}" maxlength="255">
                        </div>
                        <div class="pumk-field">
                            <label for="jaminan_no_sertifikat">Nomor Sertifikat</label>
                            <input id="jaminan_no_sertifikat" name="jaminan_no_sertifikat" class="pumk-input" value="{{ old('jaminan_no_sertifikat', $pinjaman->jaminan_no_sertifikat) }}" maxlength="255">
                        </div>
                        <div class="pumk-field">
                            <label for="jaminan_luas">Luas</label>
                            <input id="jaminan_luas" name="jaminan_luas" class="pumk-input" value="{{ old('jaminan_luas', $pinjaman->jaminan_luas) }}" maxlength="255" placeholder="Contoh: 380 m²">
                        </div>
                        <div class="pumk-field">
                            <label for="jaminan_atas_nama">Atas Nama</label>
                            <input id="jaminan_atas_nama" name="jaminan_atas_nama" class="pumk-input" value="{{ old('jaminan_atas_nama', $pinjaman->jaminan_atas_nama) }}" maxlength="255">
                        </div>
                        <div class="pumk-field md:col-span-2 lg:col-span-3">
                            <label for="jaminan_alamat">Alamat Jaminan</label>
                            <textarea id="jaminan_alamat" name="jaminan_alamat" class="pumk-textarea" maxlength="3000">{{ old('jaminan_alamat', $pinjaman->jaminan_alamat) }}</textarea>
                        </div>
                    </div>
                </details>
            </section>

            <div class="umkm-sticky-actions">
                <a href="{{ request()->boolean('return_to_profile') ? route('pumk-admin.umkm-binaan.show', $mitra) : ($editing ? route('pumk-admin.mitra.show', $mitra) : route('pumk-admin.mitra.index')) }}" class="pumk-secondary-button">Batal</a>
                <button type="submit" class="pumk-primary-button">{{ $editing ? 'Simpan Perubahan' : 'Simpan UMKM Binaan' }}</button>
            </div>
        </form>
        </section>

        @unless($editing)
            <section id="import-panel" class="pumk-card umkm-import-panel" role="tabpanel" data-input-panel="import" x-show="activeTab === 'import'" x-cloak>
                <h2 class="pumk-section-title">Import Excel (Input Massal)</h2>
                <p class="umkm-import-help">Unggah berkas .xlsx atau .csv maksimal 10 MB. Format harus mengikuti urutan header pada template. Data dengan nama usaha duplikat dilewati; baris valid tetap disimpan dan hasil setiap baris ditampilkan setelah proses.</p>
                <p>
                    <a href="{{ route('pumk-admin.umkm-binaan.template') }}" class="pumk-secondary-button">Download Template Excel (.xlsx)</a>
                </p>
                @if(session('import_result'))
                    @php
                        $importResult = session('import_result');
                    @endphp
                    <div class="pumk-alert {{ $importResult['failed'] > 0 ? 'error' : 'success' }}" role="status">
                        Impor selesai: {{ $importResult['imported'] }} berhasil, {{ $importResult['duplicate'] }} duplikat dilewati, {{ $importResult['failed'] }} gagal.
                        @if(!empty($importResult['rows']))
                            <ul class="umkm-import-errors">
                                @foreach($importResult['rows'] as $rowResult)
                                    <li>Baris {{ $rowResult['row'] }} — {{ $rowResult['message'] }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
                <form method="POST" action="{{ route('pumk-admin.umkm-binaan.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="pumk-field">
                        <label for="import_file">File Excel atau CSV</label>
                        <input id="import_file" type="file" name="import_file" class="pumk-input" accept=".xlsx,.csv" required>
                        @error('import_file')<span class="pumk-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="pumk-form-actions">
                        <span></span>
                        <button type="submit" class="pumk-primary-button">Validasi dan Import Data</button>
                    </div>
                </form>
            </section>
        @endunless
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('pumk-mitra-form')?.addEventListener('submit', (event) => {
                const replacing = [...document.querySelectorAll('[data-existing-document="1"]')]
                    .some((file) => file.files.length > 0);
                if (replacing && !window.confirm('Dokumen lama pada jenis yang dipilih akan diganti. Lanjutkan?')) {
                    event.preventDefault();
                }
            });
            document.querySelectorAll('[data-contract-document]').forEach((wrapper) => {
                const contract = document.getElementById(wrapper.dataset.contractInput);
                const file = wrapper.querySelector('input[type="file"]');
                const help = wrapper.querySelector('[data-document-help]');
                if (!contract || !file) return;
                const sync = () => {
                    file.disabled = contract.value.trim() === '';
                    if (file.disabled && help) help.textContent = 'Isi nomor kontrak terlebih dahulu untuk mengaktifkan upload.';
                };
                contract.addEventListener('input', sync);
                sync();
            });

            const addressFields = (group) => Object.fromEntries(
                [...document.querySelectorAll(`[data-address-field^="${group}."]`)]
                    .map((field) => [field.dataset.addressField.split('.')[1], field]),
            );
            const ktp = addressFields('ktp');
            const addressSummary = (group) => {
                const fields = addressFields(group);
                const parts = [
                    fields.lengkap?.value,
                    fields.desa?.value ? `Desa/Kelurahan ${fields.desa.value}` : '',
                    [fields.rt?.value && `RT ${fields.rt.value}`, fields.rw?.value && `RW ${fields.rw.value}`].filter(Boolean).join('/'),
                    fields.kecamatan?.value ? `Kecamatan ${fields.kecamatan.value}` : '',
                    fields.kabupaten_kota?.value,
                    fields.provinsi?.value,
                ].filter(Boolean);
                return [...new Set(parts)].join(', ');
            };
            const syncAddressOptions = () => {
                const businessSame = document.querySelector('[data-address-toggle="usaha"]')?.checked;
                if (businessSame) {
                    Object.entries(ktp).forEach(([field, source]) => {
                        const destination = document.querySelector(`[data-address-field="usaha.${field}"]`);
                        if (destination) destination.value = source.value;
                    });
                }
                ['domisili', 'usaha'].forEach((group) => {
                    const summary = document.querySelector(`[data-address-summary="${group}"]`);
                    if (summary) summary.textContent = addressSummary('ktp') || 'Isi alamat KTP untuk melihat pratinjau.';
                });
            };
            document.querySelectorAll('[data-address-toggle]').forEach((toggle) => toggle.addEventListener('change', syncAddressOptions));
            document.querySelectorAll('[data-address-field^="ktp."]').forEach((field) => field.addEventListener('input', syncAddressOptions));
            syncAddressOptions();

            document.querySelectorAll('[data-compose-address]').forEach((button) => button.addEventListener('click', () => {
                const group = button.dataset.composeAddress;
                const fields = addressFields(group);
                const parts = [
                    fields.desa?.value ? `Desa/Kelurahan ${fields.desa.value}` : '',
                    [fields.rt?.value && `RT ${fields.rt.value}`, fields.rw?.value && `RW ${fields.rw.value}`].filter(Boolean).join('/'),
                    fields.kecamatan?.value ? `Kecamatan ${fields.kecamatan.value}` : '',
                    fields.kabupaten_kota?.value,
                    fields.provinsi?.value,
                ].filter(Boolean);
                if (fields.lengkap) fields.lengkap.value = parts.join(', ');
                syncAddressOptions();
            }));

            document.querySelectorAll('[data-share-location]').forEach((button) => button.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    window.alert('Perangkat ini tidak mendukung akses lokasi.');
                    return;
                }
                navigator.geolocation.getCurrentPosition((position) => {
                    const coordinates = `${position.coords.latitude},${position.coords.longitude}`;
                    const input = document.querySelector(`[data-address-field="${button.dataset.shareLocation}.share_lok"]`);
                    if (input) input.value = `https://www.google.com/maps?q=${encodeURIComponent(coordinates)}`;
                }, () => window.alert('Lokasi tidak dapat diakses. Periksa izin GPS dan koneksi HTTPS.'), { enableHighAccuracy: true, timeout: 10000 });
            }));

            const photoInput = document.querySelector('[data-photo-input]');
            const photoPreview = document.querySelector('[data-photo-preview]');
            const photoPlaceholder = document.querySelector('[data-photo-placeholder]');
            photoInput?.addEventListener('change', () => {
                const file = photoInput.files[0];
                if (!file || !photoPreview) return;
                if (!['image/jpeg', 'image/png'].includes(file.type) || file.size > 5 * 1024 * 1024) {
                    photoInput.value = '';
                    window.alert('Pasfoto harus berupa JPG/PNG dan berukuran maksimal 5 MB.');
                    return;
                }
                photoPreview.src = URL.createObjectURL(file);
                photoPreview.hidden = false;
                if (photoPlaceholder) photoPlaceholder.hidden = true;
            });

            document.querySelectorAll('[data-file-input]').forEach((input) => {
                input.addEventListener('change', () => {
                    const fileName = input.closest('.pumk-field')?.querySelector('[data-file-name]');
                    if (fileName) fileName.textContent = input.files[0]?.name || 'Belum ada berkas dipilih';
                });
            });

            const principal = document.getElementById('sisa_pokok');
            const interest = document.getElementById('sisa_bunga');
            const total = document.getElementById('total_sisa');
            const initialSum = Number(principal?.value || 0) + Number(interest?.value || 0);
            if (total?.value.trim() && Math.abs(Number(total.value) - initialSum) > 0.001) {
                total.dataset.manual = '1';
            }
            const syncTotal = () => {
                if (!principal || !interest || !total || total.dataset.manual === '1') return;
                const principalValue = Number(principal.value || 0);
                const interestValue = Number(interest.value || 0);
                total.value = principal.value || interest.value
                    ? (principalValue + interestValue).toFixed(2)
                    : '';
            };
            principal?.addEventListener('input', syncTotal);
            interest?.addEventListener('input', syncTotal);
            total?.addEventListener('input', () => {
                if (!total.value.trim()) {
                    delete total.dataset.manual;
                    syncTotal();
                } else {
                    total.dataset.manual = '1';
                }
            });
            syncTotal();

            const disbursementDate = document.getElementById('tanggal_pencairan');
            const disbursementYear = document.getElementById('tahun_pencairan');
            if (disbursementDate?.value && disbursementYear && !disbursementYear.value) {
                disbursementYear.value = disbursementDate.value.slice(0, 4);
            }
            disbursementDate?.addEventListener('change', () => {
                if (disbursementDate.value && disbursementYear && !disbursementYear.value) {
                    disbursementYear.value = disbursementDate.value.slice(0, 4);
                }
            });

            let documentIndex = 1;
            document.querySelector('[data-add-document]')?.addEventListener('click', () => {
                const container = document.querySelector('[data-document-inputs]');
                if (!container) return;
                const field = document.createElement('div');
                field.className = 'pumk-field umkm-document-input max-w-2xl';
                field.innerHTML = `<label for="dokumen_pendukung_${documentIndex}">Dokumen Pendukung</label><label class="umkm-file-picker" for="dokumen_pendukung_${documentIndex}"><span data-file-name>Belum ada berkas dipilih</span><span class="pick-button">Pilih Berkas</span></label><input id="dokumen_pendukung_${documentIndex}" type="file" name="dokumen_pendukung[]" class="sr-only" data-file-input>`;
                field.querySelector('[data-file-input]').addEventListener('change', (event) => {
                    field.querySelector('[data-file-name]').textContent = event.currentTarget.files[0]?.name || 'Belum ada berkas dipilih';
                });
                container.appendChild(field);
                documentIndex += 1;
            });
        });
    </script>
</x-layouts.pumk-admin>
