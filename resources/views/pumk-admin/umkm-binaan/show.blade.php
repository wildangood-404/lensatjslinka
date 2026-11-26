<x-layouts.pumk-admin title="Profil UMKM Binaan">
    @include('pumk-admin.partials.module-styles')

    @php
        $selectedLoan = $mitra->pinjaman->first(
            fn ($loan) => $loan->status === \App\Models\PumkPinjaman::STATUS_AKTIF && $loan->is_active,
        ) ?? $mitra->pinjaman->first();
        $profileValue = static fn ($value) => filled($value) ? $value : 'Belum diisi';
        $formatDate = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d-m-Y') : 'Belum diisi';
        $rupiah = static fn ($value) => 'Rp '.number_format((float) ($value ?? 0), 0, ',', '.');
        $addressDetails = static function (string $prefix) use ($mitra): array {
            $legacyFallback = $prefix === 'ktp';

            return [
                'full' => $mitra->{"alamat_{$prefix}_lengkap"} ?: ($legacyFallback ? $mitra->alamat : null),
                'desa' => $mitra->{"alamat_{$prefix}_desa"},
                'rt' => $mitra->{"alamat_{$prefix}_rt"},
                'rw' => $mitra->{"alamat_{$prefix}_rw"},
                'kecamatan' => $mitra->{"alamat_{$prefix}_kecamatan"},
                'kabupaten' => $mitra->{"alamat_{$prefix}_kabupaten_kota"} ?: ($legacyFallback ? ($mitra->kabupaten_kota ?: $mitra->wilayah?->nama) : null),
                'provinsi' => $mitra->{"alamat_{$prefix}_provinsi"} ?: ($legacyFallback ? $mitra->provinsi : null),
                'share_lok' => $mitra->{"alamat_{$prefix}_share_lok"},
            ];
        };
        $ktpAddress = $addressDetails('ktp');
        $domicileAddress = $mitra->alamat_domisili_sama_ktp ? $ktpAddress : $addressDetails('domisili');
        $businessAddress = $mitra->alamat_usaha_sama_ktp ? $ktpAddress : $addressDetails('usaha');
        $googleMapsUrl = static function ($value): ?string {
            $location = trim((string) $value);
            if ($location === '') {
                return null;
            }

            if (filter_var($location, FILTER_VALIDATE_URL)) {
                $scheme = strtolower((string) parse_url($location, PHP_URL_SCHEME));
                $host = strtolower((string) parse_url($location, PHP_URL_HOST));
                $isGoogleMapsHost = $host === 'google.com'
                    || str_ends_with($host, '.google.com')
                    || $host === 'maps.app.goo.gl';

                return in_array($scheme, ['http', 'https'], true) && $isGoogleMapsHost ? $location : null;
            }

            if (preg_match('/^\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', $location, $matches)) {
                $latitude = (float) $matches[1];
                $longitude = (float) $matches[2];
                if (abs($latitude) <= 90 && abs($longitude) <= 180) {
                    return 'https://www.google.com/maps?q='.rawurlencode("{$latitude},{$longitude}");
                }
            }

            return null;
        };
        $collectibilityLabels = [
            'lancar' => 'Lancar',
            'kurang_lancar' => 'Kurang Lancar',
            'diragukan' => 'Diragukan',
            'macet' => 'Macet',
        ];
        $collectibilityClass = match ($selectedLoan?->kolektibilitas) {
            'lancar' => 'is-good',
            'kurang_lancar' => 'is-warning',
            'diragukan', 'macet' => 'is-danger',
            default => 'is-muted',
        };
        $loanStatusLabels = [
            'aktif' => 'Aktif',
            'lunas' => 'Lunas',
            'reschedule' => 'Reschedule',
            'bermasalah' => 'Bermasalah',
        ];
        $documents = $mitra->dokumenPendukung->map(function ($document) use ($mitra): array {
            $previewable = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            return [
                'kind' => 'Dokumen Pendukung',
                'filename' => $document->nama_file_asli,
                'mime' => $document->mime_type,
                'size' => $document->file_size,
                'reference' => null,
                'can_preview' => in_array($document->mime_type, $previewable, true),
                'preview_url' => route('pumk-admin.umkm-binaan.documents.view', [$mitra, $document]),
                'download_url' => route('pumk-admin.umkm-binaan.documents.download', [$mitra, $document]),
            ];
        })->concat($mitra->pinjaman->flatMap(function ($loan) use ($mitra): array {
            $previewable = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            return $loan->dokumenKontrak->map(function ($document) use ($mitra, $loan, $previewable): array {
                return [
                    'kind' => \App\Models\PumkPinjamanDokumen::TYPES[$document->jenis_dokumen] ?? 'Dokumen Kontrak',
                    'filename' => $document->nama_file_asli,
                    'mime' => $document->mime_type,
                    'size' => $document->file_size,
                    'reference' => $loan->spj_awal,
                    'can_preview' => in_array($document->mime_type, $previewable, true),
                    'preview_url' => route('pumk-admin.umkm-binaan.loans.documents.view', [$mitra, $loan, $document]),
                    'download_url' => route('pumk-admin.umkm-binaan.loans.documents.download', [$mitra, $loan, $document]),
                ];
            })->all();
        }))->values();
    @endphp

    <style>
        [x-cloak]{display:none!important}
        .umkm-profile-page{max-width:1280px;padding:18px;border-radius:24px;background:rgba(248,250,252,.72)}
        .umkm-profile-back{display:inline-flex;align-items:center;gap:7px;margin-bottom:14px;color:#475569;font-size:13px;font-weight:600;text-decoration:none}
        .umkm-profile-back:hover{color:#2563eb}
        .umkm-profile-hero,.umkm-profile-card{border:1px solid rgba(226,232,240,.9);border-radius:18px;background:#fff;box-shadow:0 2px 6px rgba(15,23,42,.045)}
        .umkm-profile-hero{padding:24px}
        .umkm-hero-top{display:flex;align-items:flex-start;justify-content:space-between;gap:20px}
        .umkm-hero-top h1{margin:0;color:#0f172a;font-size:28px;font-weight:700;letter-spacing:-.03em;line-height:1.25}
        .umkm-hero-subtitle{display:flex;flex-wrap:wrap;align-items:center;gap:7px;margin-top:8px;color:#64748b;font-size:12px}
        .umkm-hero-separator{color:#cbd5e1}
        .umkm-status-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 10px;font-size:10px;font-weight:700;white-space:nowrap}
        .umkm-status-badge.is-good{background:#dcfce7;color:#166534}
        .umkm-status-badge.is-warning{background:#fef3c7;color:#92400e}
        .umkm-status-badge.is-danger{background:#fee2e2;color:#991b1b}
        .umkm-status-badge.is-muted{background:#f1f5f9;color:#475569}
        .umkm-profile-tabs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:5px;margin-top:24px;border:1px solid #e2e8f0;border-radius:12px;background:#f1f5f9;padding:5px}
        .umkm-profile-tabs button{display:flex;min-height:42px;align-items:center;justify-content:center;gap:8px;border:0;border-radius:8px;background:transparent;color:#64748b;font:500 12px Poppins,sans-serif;cursor:pointer}
        .umkm-profile-tabs button.is-active{background:#fff;color:#2563eb;font-weight:700;box-shadow:0 1px 3px rgba(15,23,42,.12)}
        .umkm-profile-tabs svg{width:17px;height:17px}
        .umkm-profile-card{margin-top:16px;padding:24px}
        .umkm-profile-heading{display:flex;align-items:flex-start;gap:12px;margin-bottom:20px}
        .umkm-profile-icon{display:grid;width:40px;height:40px;flex:none;place-items:center;border:1px solid #dbeafe;border-radius:12px;background:#eff6ff;color:#2563eb}
        .umkm-profile-icon svg{width:20px;height:20px}
        .umkm-profile-heading h2{margin:0;color:#0f172a;font-size:16px;font-weight:700}
        .umkm-profile-heading p{margin:4px 0 0;color:#64748b;font-size:12px}
        .umkm-profile-photo-frame{display:flex;aspect-ratio:3/4;max-width:200px;align-items:center;justify-content:center;overflow:hidden;border:1px solid #e2e8f0;border-radius:13px;background:#f8fafc;color:#94a3b8}
        .umkm-profile-photo-frame img{width:100%;height:100%;object-fit:cover}
        .umkm-profile-photo-empty{display:flex;flex-direction:column;align-items:center;gap:10px;text-align:center}
        .umkm-profile-photo-empty svg{width:42px;height:42px;color:#94a3b8}
        .umkm-profile-photo-empty span{font-size:11px}
        .umkm-profile-rows{overflow:hidden;border:1px solid #e2e8f0;border-radius:12px}
        .umkm-profile-row{display:grid;grid-template-columns:minmax(150px,.8fr) minmax(0,1.4fr);gap:16px;padding:11px 14px}
        .umkm-profile-row:nth-child(even){background:#f8fafc}
        .umkm-profile-row>span{color:#64748b;font-size:11px;font-weight:600}
        .umkm-profile-row>strong{min-width:0;color:#1e293b;font-size:12px;font-weight:600;overflow-wrap:anywhere;line-height:1.55}
        .umkm-profile-address{height:100%;border:1px solid #e2e8f0;border-radius:14px;background:#fff;padding:18px}
        .umkm-profile-address-heading{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}
        .umkm-profile-address-heading h3{margin:0;color:#334155;font-size:11px;font-weight:700;letter-spacing:.07em}
        .umkm-address-details{overflow:hidden;border:1px solid #e2e8f0;border-radius:10px}
        .umkm-address-row{display:grid;grid-template-columns:minmax(115px,.8fr) minmax(0,1.4fr);gap:12px;padding:9px 11px}
        .umkm-address-row:nth-child(even){background:#f8fafc}
        .umkm-address-row span{color:#64748b;font-size:10px;font-weight:600}
        .umkm-address-row strong{color:#334155;font-size:11px;font-weight:500;line-height:1.5;overflow-wrap:anywhere}
        .umkm-map-link{display:inline-flex;align-items:center;gap:6px;margin-top:12px;border-radius:8px;background:#eff6ff;padding:8px 11px;color:#2563eb;font-size:11px;font-weight:700;text-decoration:none}
        .umkm-map-link:hover{background:#dbeafe}
        .umkm-map-link svg{width:15px;height:15px}
        .umkm-profile-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
        .umkm-profile-item{min-width:0;border:1px solid #e2e8f0;border-radius:11px;background:#fff;padding:13px}
        .umkm-profile-item span{display:block;color:#64748b;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em}
        .umkm-profile-item strong{display:block;margin-top:6px;color:#0f172a;font-size:13px;font-weight:600;overflow-wrap:anywhere;line-height:1.55}
        .umkm-finance-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
        .umkm-finance-card{min-width:0;border:1px solid #dbeafe;border-radius:12px;background:linear-gradient(135deg,#f8fbff,#eff6ff);padding:14px}
        .umkm-finance-card span{display:block;color:#64748b;font-size:10px;font-weight:600;line-height:1.5}
        .umkm-finance-card strong{display:block;margin-top:7px;color:#0f172a;font-size:15px;font-weight:700;overflow-wrap:anywhere}
        .umkm-table-wrap{overflow-x:auto;border:1px solid #e2e8f0;border-radius:12px}
        .umkm-profile-table{width:100%;border-collapse:collapse;min-width:720px}
        .umkm-profile-table th{background:#f8fafc;color:#64748b;font-size:10px;font-weight:700;text-align:left;text-transform:uppercase;letter-spacing:.04em}
        .umkm-profile-table th,.umkm-profile-table td{border-bottom:1px solid #e2e8f0;padding:11px 12px;vertical-align:middle}
        .umkm-profile-table td{color:#334155;font-size:11px}
        .umkm-profile-table tbody tr:last-child td{border-bottom:0}
        .umkm-document-list{display:grid;gap:9px}
        .umkm-document-card{display:flex;align-items:center;justify-content:space-between;gap:15px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;padding:13px}
        .umkm-document-meta{display:flex;min-width:0;align-items:center;gap:12px}
        .umkm-document-icon{display:grid;width:38px;height:38px;flex:none;place-items:center;border-radius:10px;background:#eff6ff;color:#2563eb}
        .umkm-document-icon svg{width:19px;height:19px}
        .umkm-document-meta strong{display:block;color:#1e293b;font-size:12px;font-weight:700}
        .umkm-document-meta span{display:block;margin-top:3px;color:#64748b;font-size:10px;overflow-wrap:anywhere}
        .umkm-document-actions{display:flex;flex:none;gap:7px}
        .umkm-document-actions a{display:inline-flex;min-height:33px;align-items:center;justify-content:center;border:1px solid #dbeafe;border-radius:8px;background:#eff6ff;padding:0 11px;color:#2563eb;font-size:10px;font-weight:700;text-decoration:none}
        .umkm-document-actions a:hover{background:#dbeafe}
        .umkm-document-actions a.is-download{border-color:#e2e8f0;background:#f8fafc;color:#475569}
        .umkm-profile-empty{border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;padding:28px 18px;color:#64748b;text-align:center}
        .umkm-profile-empty svg{width:32px;height:32px;margin:0 auto 9px;color:#94a3b8}
        .umkm-profile-empty strong{display:block;color:#334155;font-size:12px}
        .umkm-profile-empty p{margin:5px 0 0;font-size:11px}
        .umkm-profile-section-label{margin:20px 0 10px;color:#334155;font-size:12px;font-weight:700}
        .umkm-monitoring-callout{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-top:16px;border:1px solid #dbeafe;border-radius:12px;background:#f8fbff;padding:14px}
        .umkm-monitoring-callout p{margin:0;color:#475569;font-size:11px;line-height:1.55}
        .umkm-monitoring-callout a{display:inline-flex;min-height:36px;align-items:center;border-radius:8px;background:#2563eb;padding:0 12px;color:#fff;font-size:11px;font-weight:700;text-decoration:none}
        .umkm-profile-page .pumk-primary-button{white-space:nowrap}
        @media(max-width:900px){.umkm-finance-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(max-width:760px){.umkm-profile-page{padding:12px;border-radius:16px}.umkm-profile-hero,.umkm-profile-card{padding:18px}.umkm-hero-top{flex-direction:column}.umkm-profile-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.umkm-profile-tabs button{font-size:11px}.umkm-profile-tabs svg{width:15px;height:15px}}
        @media(max-width:540px){.umkm-profile-tabs button{gap:4px;flex-direction:column;min-height:53px;padding:6px 3px;font-size:9px}.umkm-profile-row,.umkm-address-row{grid-template-columns:1fr;gap:4px}.umkm-finance-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.umkm-document-card{align-items:flex-start;flex-direction:column}.umkm-document-actions{width:100%;padding-left:50px}.umkm-document-actions a{flex:1}}
        @media(max-width:400px){.umkm-profile-grid{grid-template-columns:1fr}.umkm-hero-top h1{font-size:23px}}
    </style>

    <div class="pumk-page umkm-profile-page bg-slate-50/70 p-4 md:p-8" x-data="{ tab: 'profil' }">
        <a href="{{ route('pumk-admin.umkm-binaan.index') }}" class="umkm-profile-back">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6M9 12h11"/></svg>
            Kembali ke Daftar
        </a>

        <section class="umkm-profile-hero" aria-label="Ringkasan profil UMKM Binaan">
            <div class="umkm-hero-top">
                <div>
                    <h1>{{ $mitra->nama_mitra }}</h1>
                    <div class="umkm-hero-subtitle">
                        <span>{{ $profileValue($mitra->nama_pemilik) }}</span>
                        <span class="umkm-hero-separator" aria-hidden="true">•</span>
                        <span>NIK {{ $profileValue($mitra->nik) }}</span>
                        <span class="umkm-hero-separator" aria-hidden="true">•</span>
                        <span>{{ $mitra->wilayah_label ?? 'Wilayah belum ditentukan' }}</span>
                        <span class="umkm-status-badge {{ $collectibilityClass }}">
                            {{ $collectibilityLabels[$selectedLoan?->kolektibilitas] ?? 'Kolektibilitas belum ditentukan' }}
                        </span>
                    </div>
                </div>
                <a href="{{ route('pumk-admin.umkm-binaan.edit', [$mitra, 'pinjaman' => $selectedLoan?->id, 'return_to_profile' => 1]) }}" class="pumk-primary-button">Edit Profil</a>
            </div>
            <nav class="umkm-profile-tabs" role="tablist" aria-label="Navigasi profil UMKM Binaan">
                <button type="button" role="tab" id="profile-tab" aria-controls="profile-panel" :aria-selected="tab === 'profil'" :class="{ 'is-active': tab === 'profil' }" @click="tab = 'profil'">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.5h16v13H4zM8 9h8M8 13h5"/></svg>
                    Profil
                </button>
                <button type="button" role="tab" id="receivable-tab" aria-controls="receivable-panel" :aria-selected="tab === 'piutang'" :class="{ 'is-active': tab === 'piutang' }" @click="tab = 'piutang'">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 7.5h17v12h-17zM7 7.5V5h10v2.5M7 12h4m-4 3.5h7"/></svg>
                    Kartu Piutang
                </button>
                <button type="button" role="tab" id="monitoring-tab" aria-controls="monitoring-panel" :aria-selected="tab === 'monitoring'" :class="{ 'is-active': tab === 'monitoring' }" @click="tab = 'monitoring'">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 18.5V5.5m0 13h16M8 15v-4m4 4V7m4 8v-6"/></svg>
                    Monitoring
                </button>
            </nav>
        </section>

        <div id="profile-panel" role="tabpanel" aria-labelledby="profile-tab" x-show="tab === 'profil'" x-cloak>
            <section class="umkm-profile-card" aria-labelledby="identity-heading">
                <div class="umkm-profile-heading">
                    <span class="umkm-profile-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.12a7.5 7.5 0 0 1 15 0"/></svg></span>
                    <div><h2 id="identity-heading">Identitas Utama &amp; Biodata Pemilik</h2><p>Informasi pemilik dan kontak yang tersimpan di profil.</p></div>
                </div>
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <div class="lg:col-span-3">
                        <div class="umkm-profile-photo-frame">
                            @if($mitra->pasfoto)
                                <img src="{{ route('pumk-admin.umkm-binaan.photo', $mitra) }}" alt="Pasfoto pemilik {{ $mitra->nama_mitra }}">
                            @else
                                <div class="umkm-profile-photo-empty">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.12a7.5 7.5 0 0 1 15 0"/></svg>
                                    <span>Belum ada pasfoto</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="lg:col-span-9">
                        <div class="umkm-profile-rows">
                            <div class="umkm-profile-row"><span>Nama Usaha / UMKM Binaan</span><strong>{{ $mitra->nama_mitra }}</strong></div>
                            <div class="umkm-profile-row"><span>Nama Lengkap Pemilik</span><strong>{{ $profileValue($mitra->nama_pemilik) }}</strong></div>
                            <div class="umkm-profile-row"><span>NIK</span><strong>{{ $profileValue($mitra->nik) }}</strong></div>
                            <div class="umkm-profile-row"><span>Jenis Kelamin</span><strong>{{ $profileValue($mitra->jenis_kelamin) }}</strong></div>
                            <div class="umkm-profile-row"><span>Tempat, Tanggal Lahir</span><strong>{{ collect([$mitra->tempat_lahir, $mitra->tanggal_lahir?->format('d-m-Y')])->filter()->implode(', ') ?: 'Belum diisi' }}</strong></div>
                            <div class="umkm-profile-row"><span>No. Ponsel / WhatsApp</span><strong>{{ $profileValue($mitra->no_telepon_encrypted) }}</strong></div>
                            <div class="umkm-profile-row"><span>Email</span><strong>{{ $profileValue($mitra->email) }}</strong></div>
                            <div class="umkm-profile-row"><span>Nomor Rekening</span><strong>{{ $profileValue($mitra->no_rekening_encrypted) }}</strong></div>
                            <div class="umkm-profile-row"><span>Status Mitra</span><strong><span class="umkm-status-badge {{ $mitra->is_active ? 'is-good' : 'is-muted' }}">{{ $mitra->is_active ? 'Aktif' : 'Arsip' }}</span></strong></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="umkm-profile-card" aria-labelledby="address-heading">
                <div class="umkm-profile-heading">
                    <span class="umkm-profile-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.2"/></svg></span>
                    <div><h2 id="address-heading">Alamat KTP &amp; Domisili</h2><p>Rincian alamat sesuai KTP dan tempat tinggal saat ini.</p></div>
                </div>
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    @foreach([
                        ['title' => 'ALAMAT SESUAI KTP', 'address' => $ktpAddress, 'badge' => null],
                        ['title' => 'ALAMAT DOMISILI SAAT INI', 'address' => $domicileAddress, 'badge' => $mitra->alamat_domisili_sama_ktp ? 'Sama dengan KTP' : 'Alamat berbeda'],
                    ] as $addressCard)
                        <div class="umkm-profile-address">
                            <div class="umkm-profile-address-heading">
                                <h3>{{ $addressCard['title'] }}</h3>
                                @if($addressCard['badge'])
                                    <span class="umkm-status-badge {{ $mitra->alamat_domisili_sama_ktp ? 'is-good' : 'is-muted' }}">{{ $addressCard['badge'] }}</span>
                                @endif
                            </div>
                            <div class="umkm-address-details">
                                <div class="umkm-address-row"><span>Alamat Lengkap</span><strong>{{ $profileValue($addressCard['address']['full']) }}</strong></div>
                                <div class="umkm-address-row"><span>Desa / Kelurahan</span><strong>{{ $profileValue($addressCard['address']['desa']) }}</strong></div>
                                <div class="umkm-address-row"><span>RT / RW</span><strong>{{ collect([$addressCard['address']['rt'] ? 'RT '.$addressCard['address']['rt'] : null, $addressCard['address']['rw'] ? 'RW '.$addressCard['address']['rw'] : null])->filter()->implode(' / ') ?: 'Belum diisi' }}</strong></div>
                                <div class="umkm-address-row"><span>Kecamatan</span><strong>{{ $profileValue($addressCard['address']['kecamatan']) }}</strong></div>
                                <div class="umkm-address-row"><span>Kab/Kota (Wilayah)</span><strong>{{ $profileValue($addressCard['address']['kabupaten']) }}</strong></div>
                                <div class="umkm-address-row"><span>Provinsi</span><strong>{{ $profileValue($addressCard['address']['provinsi']) }}</strong></div>
                            </div>
                            @php($mapUrl = $googleMapsUrl($addressCard['address']['share_lok']))
                            @if($mapUrl)
                                <a href="{{ $mapUrl }}" class="umkm-map-link" target="_blank" rel="noopener noreferrer">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.2"/></svg>
                                    Buka Google Maps
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="umkm-profile-card" aria-labelledby="business-heading">
                <div class="umkm-profile-heading">
                    <span class="umkm-profile-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 20h18M5 20V8l7-4 7 4v12M9 11h.01M15 11h.01M9 15h.01M15 15h.01"/></svg></span>
                    <div><h2 id="business-heading">Identitas Usaha &amp; Lokasi Usaha</h2><p>Informasi kegiatan dan lokasi operasional UMKM binaan.</p></div>
                </div>
                <div class="umkm-profile-grid">
                    <div class="umkm-profile-item"><span>Nama Usaha</span><strong>{{ $mitra->nama_mitra }}</strong></div>
                    <div class="umkm-profile-item"><span>Sektor Usaha</span><strong>{{ $profileValue($mitra->sektorUsaha?->nama ?? $mitra->sektor_sumber) }}</strong></div>
                    <div class="umkm-profile-item"><span>Jenis Usaha</span><strong>{{ $profileValue($mitra->jenis_usaha) }}</strong></div>
                </div>
                <div class="umkm-profile-address mt-4">
                    <div class="umkm-profile-address-heading">
                        <h3>ALAMAT USAHA</h3>
                        <span class="umkm-status-badge {{ $mitra->alamat_usaha_sama_ktp ? 'is-good' : 'is-muted' }}">{{ $mitra->alamat_usaha_sama_ktp ? 'Sama dengan KTP' : 'Alamat berbeda' }}</span>
                    </div>
                    <div class="umkm-address-details">
                        <div class="umkm-address-row"><span>Alamat Lengkap</span><strong>{{ $profileValue($businessAddress['full']) }}</strong></div>
                        <div class="umkm-address-row"><span>Desa / Kelurahan</span><strong>{{ $profileValue($businessAddress['desa']) }}</strong></div>
                        <div class="umkm-address-row"><span>RT / RW</span><strong>{{ collect([$businessAddress['rt'] ? 'RT '.$businessAddress['rt'] : null, $businessAddress['rw'] ? 'RW '.$businessAddress['rw'] : null])->filter()->implode(' / ') ?: 'Belum diisi' }}</strong></div>
                        <div class="umkm-address-row"><span>Kecamatan</span><strong>{{ $profileValue($businessAddress['kecamatan']) }}</strong></div>
                        <div class="umkm-address-row"><span>Kab/Kota</span><strong>{{ $profileValue($businessAddress['kabupaten']) }}</strong></div>
                        <div class="umkm-address-row"><span>Provinsi</span><strong>{{ $profileValue($businessAddress['provinsi']) }}</strong></div>
                    </div>
                    @if($mapUrl = $googleMapsUrl($businessAddress['share_lok']))
                        <a href="{{ $mapUrl }}" class="umkm-map-link" target="_blank" rel="noopener noreferrer">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.2"/></svg>
                            Share Lokasi Usaha
                        </a>
                    @endif
                </div>
            </section>

            <section class="umkm-profile-card" aria-labelledby="loan-summary-heading">
                <div class="umkm-profile-heading">
                    <span class="umkm-profile-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 7.5h17v12h-17zM7 7.5V5h10v2.5M7 12h4m-4 3.5h7"/></svg></span>
                    <div><h2 id="loan-summary-heading">Ringkasan Data Pinjaman</h2><p>Ringkasan saldo dan kontrak pinjaman terbaru.</p></div>
                </div>
                @if($selectedLoan)
                    <div class="umkm-finance-grid">
                        <div class="umkm-finance-card"><span>Nilai Pokok Pencairan</span><strong>{{ $rupiah($selectedLoan->pinjaman_pokok) }}</strong></div>
                        <div class="umkm-finance-card"><span>Sisa Pinjaman Pokok</span><strong>{{ $rupiah($selectedLoan->sisa_pokok) }}</strong></div>
                        <div class="umkm-finance-card"><span>Sisa Pinjaman Bunga</span><strong>{{ $rupiah($selectedLoan->sisa_bunga) }}</strong></div>
                        <div class="umkm-finance-card"><span>Total Sisa Pinjaman</span><strong>{{ $rupiah($selectedLoan->total_sisa) }}</strong></div>
                        <div class="umkm-finance-card"><span>Denda</span><strong>{{ $rupiah($selectedLoan->denda) }}</strong></div>
                    </div>
                    <div class="umkm-profile-grid mt-4">
                        <div class="umkm-profile-item"><span>Nomor SPJ Awal</span><strong>{{ $profileValue($selectedLoan->spj_awal) }}</strong></div>
                        <div class="umkm-profile-item"><span>Tanggal Pencairan</span><strong>{{ $formatDate($selectedLoan->tanggal_pencairan) }}</strong></div>
                        <div class="umkm-profile-item"><span>Tahun Pencairan</span><strong>{{ $profileValue($selectedLoan->tahun_pencairan) }}</strong></div>
                        <div class="umkm-profile-item"><span>Kolektibilitas</span><strong>{{ $collectibilityLabels[$selectedLoan->kolektibilitas] ?? 'Belum diisi' }}</strong></div>
                        <div class="umkm-profile-item"><span>Status Pinjaman</span><strong>{{ $loanStatusLabels[$selectedLoan->status_pinjaman] ?? \Illuminate\Support\Str::headline((string) $selectedLoan->status) }}</strong></div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" class="pumk-secondary-button" @click="tab = 'piutang'">Lihat Rincian Kartu Piutang</button>
                    </div>
                @else
                    <div class="umkm-profile-empty"><strong>Belum ada data pinjaman</strong><p>Ringkasan pinjaman akan tampil setelah data pinjaman ditambahkan.</p></div>
                @endif
            </section>

            <section class="umkm-profile-card" aria-labelledby="documents-heading">
                <div class="umkm-profile-heading">
                    <span class="umkm-profile-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3.75h7l4 4v12.5H7zM14 4v4h4M10 13h5m-5 3.5h5"/></svg></span>
                    <div><h2 id="documents-heading">Berkas &amp; Dokumen UMKM Binaan</h2><p>Dokumen tersimpan untuk mitra ini. Untuk menambah atau mengganti berkas, gunakan tombol Edit Profil.</p></div>
                </div>
                @if($documents->isNotEmpty())
                    <div class="umkm-document-list">
                        @foreach($documents as $document)
                            <div class="umkm-document-card">
                                <div class="umkm-document-meta">
                                    <span class="umkm-document-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3.75h7l4 4v12.5H7zM14 4v4h4M10 13h5m-5 3.5h5"/></svg></span>
                                    <div>
                                        <strong>{{ $document['kind'] }}{{ $document['reference'] ? ' · '.$document['reference'] : '' }}</strong>
                                        <span>{{ $document['filename'] }} · {{ number_format($document['size'] / 1024, 0, ',', '.') }} KB</span>
                                    </div>
                                </div>
                                <div class="umkm-document-actions">
                                    @if($document['can_preview'])
                                        <a href="{{ $document['preview_url'] }}" target="_blank" rel="noopener noreferrer">Lihat</a>
                                    @endif
                                    <a class="is-download" href="{{ $document['download_url'] }}">Unduh</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="umkm-profile-empty">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3.75h7l4 4v12.5H7zM14 4v4h4M10 13h5m-5 3.5h5"/></svg>
                        <strong>Belum ada berkas atau dokumen</strong>
                        <p>Dokumen pendukung dan SPJ dapat ditambahkan melalui Edit Profil.</p>
                    </div>
                @endif
            </section>
        </div>

        <section id="receivable-panel" class="umkm-profile-card" role="tabpanel" aria-labelledby="receivable-tab" x-show="tab === 'piutang'" x-cloak>
            <div class="umkm-profile-heading">
                <span class="umkm-profile-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 7.5h17v12h-17zM7 7.5V5h10v2.5M7 12h4m-4 3.5h7"/></svg></span>
                <div><h2>Kartu Piutang &amp; Riwayat Pinjaman</h2><p>Rincian saldo dan riwayat pembayaran pinjaman mitra.</p></div>
            </div>
            @if($selectedLoan)
                <h3 class="umkm-profile-section-label">Ringkasan Pinjaman Terpilih · {{ $selectedLoan->spj_awal ?: 'SPJ belum diisi' }}</h3>
                <div class="umkm-finance-grid">
                    <div class="umkm-finance-card"><span>Nilai Pokok Pencairan</span><strong>{{ $rupiah($selectedLoan->pinjaman_pokok) }}</strong></div>
                    <div class="umkm-finance-card"><span>Sisa Pinjaman Pokok</span><strong>{{ $rupiah($selectedLoan->sisa_pokok) }}</strong></div>
                    <div class="umkm-finance-card"><span>Sisa Pinjaman Bunga</span><strong>{{ $rupiah($selectedLoan->sisa_bunga) }}</strong></div>
                    <div class="umkm-finance-card"><span>Total Sisa Pinjaman</span><strong>{{ $rupiah($selectedLoan->total_sisa) }}</strong></div>
                    <div class="umkm-finance-card"><span>Denda</span><strong>{{ $rupiah($selectedLoan->denda) }}</strong></div>
                </div>
                <div class="umkm-profile-grid mt-4">
                    <div class="umkm-profile-item"><span>Nomor SPJ Awal</span><strong>{{ $profileValue($selectedLoan->spj_awal) }}</strong></div>
                    <div class="umkm-profile-item"><span>Tanggal Pencairan</span><strong>{{ $formatDate($selectedLoan->tanggal_pencairan) }}</strong></div>
                    <div class="umkm-profile-item"><span>Tahun Pencairan</span><strong>{{ $profileValue($selectedLoan->tahun_pencairan) }}</strong></div>
                    <div class="umkm-profile-item"><span>Kolektibilitas</span><strong>{{ $collectibilityLabels[$selectedLoan->kolektibilitas] ?? 'Belum diisi' }}</strong></div>
                    <div class="umkm-profile-item"><span>Status Pinjaman</span><strong>{{ $loanStatusLabels[$selectedLoan->status_pinjaman] ?? \Illuminate\Support\Str::headline((string) $selectedLoan->status) }}</strong></div>
                </div>
                <div class="mt-4 flex justify-end">
                    <a href="{{ route('pumk-admin.mitra.show', [$mitra, 'pinjaman' => $selectedLoan->id]) }}" class="pumk-primary-button">Buka Halaman Penuh Kartu Piutang</a>
                </div>

                @if($mitra->pinjaman->isNotEmpty())
                    <h3 class="umkm-profile-section-label">Riwayat Pinjaman</h3>
                    <div class="umkm-table-wrap">
                        <table class="umkm-profile-table">
                            <thead><tr><th>Nomor SPJ</th><th>Tanggal Pencairan</th><th>Pokok Pencairan</th><th>Total Sisa</th><th>Kolektibilitas</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @foreach($mitra->pinjaman as $loan)
                                    <tr>
                                        <td>{{ $loan->spj_awal ?: 'Belum diisi' }}</td>
                                        <td>{{ $formatDate($loan->tanggal_pencairan) }}</td>
                                        <td>{{ $rupiah($loan->pinjaman_pokok) }}</td>
                                        <td>{{ $rupiah($loan->total_sisa) }}</td>
                                        <td>{{ $collectibilityLabels[$loan->kolektibilitas] ?? 'Belum dihitung' }}</td>
                                        <td>{{ $loanStatusLabels[$loan->status_pinjaman] ?? \Illuminate\Support\Str::headline((string) $loan->status) }}</td>
                                        <td><a href="{{ route('pumk-admin.mitra.show', [$mitra, 'pinjaman' => $loan->id]) }}" class="pumk-table-action">Buka</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <h3 class="umkm-profile-section-label">Riwayat Angsuran · {{ $selectedLoan->spj_awal ?: 'Pinjaman terpilih' }}</h3>
                @if($selectedLoan->angsuran->isNotEmpty())
                    <div class="umkm-table-wrap">
                        <table class="umkm-profile-table">
                            <thead><tr><th>Periode</th><th>Pokok</th><th>Bunga</th><th>Denda</th><th>Total</th></tr></thead>
                            <tbody>
                                @foreach($selectedLoan->angsuran as $payment)
                                    <tr>
                                        <td>{{ $payment->periode?->format('m/Y') ?? '—' }}</td>
                                        <td>{{ $rupiah($payment->pokok) }}</td>
                                        <td>{{ $rupiah($payment->bunga) }}</td>
                                        <td>{{ $rupiah($payment->denda) }}</td>
                                        <td>{{ $rupiah($payment->total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="umkm-profile-empty"><strong>Belum ada riwayat angsuran</strong><p>Transaksi angsuran yang tercatat akan tampil di bagian ini.</p></div>
                @endif
            @else
                <div class="umkm-profile-empty"><strong>Belum ada data kartu piutang</strong><p>Data pinjaman akan tampil setelah ditambahkan melalui Edit Profil.</p></div>
            @endif
        </section>

        <section id="monitoring-panel" class="umkm-profile-card" role="tabpanel" aria-labelledby="monitoring-tab" x-show="tab === 'monitoring'" x-cloak>
            <div class="umkm-profile-heading">
                <span class="umkm-profile-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 18.5V5.5m0 13h16M8 15v-4m4 4V7m4 8v-6"/></svg></span>
                <div><h2>Riwayat Monitoring</h2><p>Snapshot posisi pinjaman mitra dari proses monitoring yang tersimpan.</p></div>
            </div>
            @if($monitoringHistory->isNotEmpty())
                <div class="umkm-table-wrap">
                    <table class="umkm-profile-table">
                        <thead><tr><th>Tanggal Snapshot</th><th>Nomor SPJ</th><th>Saldo Pokok</th><th>Saldo Bunga</th><th>Kolektibilitas</th><th>Sumber</th></tr></thead>
                        <tbody>
                            @foreach($monitoringHistory as $snapshot)
                                <tr>
                                    <td>{{ $snapshot->report?->as_of_date?->format('d-m-Y') ?? '—' }}</td>
                                    <td>{{ $snapshot->pinjaman?->spj_awal ?: 'Belum diisi' }}</td>
                                    <td>{{ $rupiah($snapshot->saldo_pokok) }}</td>
                                    <td>{{ $rupiah($snapshot->saldo_bunga) }}</td>
                                    <td>{{ $collectibilityLabels[$snapshot->kolektibilitas] ?? 'Belum dihitung' }}</td>
                                    <td>{{ \Illuminate\Support\Str::headline($snapshot->source_kind) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="umkm-profile-empty">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 18.5V5.5m0 13h16M8 15v-4m4 4V7m4 8v-6"/></svg>
                    <strong>Belum ada riwayat snapshot monitoring</strong>
                    <p>Belum ada laporan monitoring tersimpan untuk UMKM binaan ini.</p>
                </div>
            @endif
            <div class="umkm-monitoring-callout">
                <p>Catatan kunjungan per mitra belum tersedia. Unggah laporan monitoring untuk memperbarui snapshot posisi pinjaman.</p>
                <a href="{{ route('pumk-admin.monitoring.upload') }}">Buka Upload Monitoring</a>
            </div>
        </section>
    </div>
</x-layouts.pumk-admin>
