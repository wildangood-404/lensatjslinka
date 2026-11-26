<x-layouts.admin :title="$program->exists ? 'Detail Program' : 'Buat Program Baru'">
    @php
        $routePrefix = $routePrefix ?? 'admin';
        $isSuperAdmin = (bool) ($superAdminView ?? false);
        $legacyForm = $legacyForm ?? false;
        $readonly = ! $editable;
        $documents = $program->documents ?? collect();
        $storedGoals = $program->relationLoaded('tujuan') ? $program->tujuan : collect();
        $storedGoalText = filled($program->tujuan_program)
            ? $program->tujuan_program
            : $storedGoals->pluck('deskripsi')->filter()->implode("\n\n");
        $storedAssistanceItems = $program->relationLoaded('assistanceItems')
            ? $program->assistanceItems
            : ($program->exists ? $program->assistanceItems()->get() : collect());
        $assistanceItemsByType = $storedAssistanceItems
            ->groupBy('tipe')
            ->map(fn ($items) => $items->map(fn ($item) => [
                'nama_item' => $item->nama_item,
                'jumlah' => $item->jumlah,
                'keterangan' => $item->keterangan,
            ])->values()->all());
        $emptyAssistanceRow = ['nama_item' => '', 'jumlah' => '', 'keterangan' => ''];
        $barangItems = old('assistance_items.barang', $assistanceItemsByType->get('barang', [$emptyAssistanceRow]));
        $jasaItems = old('assistance_items.jasa', $assistanceItemsByType->get('jasa', [$emptyAssistanceRow]));
        $barangItems = is_array($barangItems) ? array_filter($barangItems, 'is_array') : [];
        $jasaItems = is_array($jasaItems) ? array_filter($jasaItems, 'is_array') : [];
        $barangItems = $barangItems ?: [$emptyAssistanceRow];
        $jasaItems = $jasaItems ?: [$emptyAssistanceRow];
        $selectedAssistance = old('jenis_bantuan', $program->jenis_bantuan ?? []);
        $selectedAssistance = is_array($selectedAssistance) ? $selectedAssistance : [];
        $selectedVerificationAssistance = old('jenis_bantuan_verifikasi', $program->jenis_bantuan_verifikasi ?? []);
        $selectedVerificationAssistance = is_array($selectedVerificationAssistance) ? $selectedVerificationAssistance : [];
        $selectedRoutes = old('jalur_pengajuan', $program->jalur_pengajuan ?? []);
        $selectedRoutes = is_array($selectedRoutes) ? $selectedRoutes : [];
        $vendorRows = old('vendors', $program->verificationVendors->where('tipe', 'barang')->map(fn ($vendor) => [
            'nama_vendor' => $vendor->nama_vendor,
            'rincian_barang' => $vendor->rincian_barang,
            'harga' => number_format((float) $vendor->harga, 0, ',', '.'),
        ])->all());
        $vendorRows = is_array($vendorRows) && $vendorRows !== [] ? $vendorRows : [['nama_vendor' => '', 'rincian_barang' => '', 'harga' => '']];
        $serviceVendorRows = old('service_vendors', $program->verificationVendors->where('tipe', 'jasa')->map(fn ($vendor) => [
            'nama_vendor' => $vendor->nama_vendor,
            'rincian_jasa' => $vendor->rincian_jasa,
            'harga' => number_format((float) $vendor->harga, 0, ',', '.'),
        ])->all());
        $serviceVendorRows = is_array($serviceVendorRows) && $serviceVendorRows !== [] ? $serviceVendorRows : [['nama_vendor' => '', 'rincian_jasa' => '', 'harga' => '']];
        $formAction = $program->exists
            ? route('admin.programs.update', $program)
            : route('admin.programs.store');
        $stageLabels = [
            'proposal' => 'Tahap 1 — Pengajuan Proposal Program',
            'survei' => 'Tahap 2 — Verifikasi Program',
            'laporan_akhir' => 'Tahap 5 — Pelaporan Akhir',
            'dokumentasi' => 'Dokumentasi Kegiatan',
        ];
        $statusLabels = [
            'menunggu_review_proposal' => 'Menunggu Review Pengajuan Proposal Program',
            'revisi' => 'Revisi Pengajuan Proposal Program',
            'proposal_approve' => 'Pengajuan Proposal Program Disetujui',
            'menunggu_review_survei' => 'Menunggu Review Verifikasi Program',
            'perbaikan' => 'Revisi Verifikasi Program',
            'survei_disetujui' => 'Verifikasi Program Disetujui',
            'tertunda' => 'Tertunda',
            'pelaksanaan' => 'Pelaksanaan',
            'kasbon_disetujui' => 'Kasbon Disetujui',
            'kasbon_ditolak' => 'Kasbon Ditolak',
            'menunggu_review_laporan_akhir' => 'Menunggu Review Laporan Akhir',
            'revisi_laporan_akhir' => 'Revisi Laporan Akhir',
            'selesai' => 'Selesai',
        ];
        $isStage1Open = ! $program->exists || in_array($program->status, ['draft', 'revisi', 'menunggu_review_proposal'], true);
        $isStage2FormActive = ! $isSuperAdmin && in_array($program->status, ['proposal_approve', 'perbaikan'], true);
        $hasStage2Summary = $program->exists
            && ! $isStage2FormActive
            && ($program->kesesuaian_rka
                || $program->kebutuhan_program
                || $documents->where('kategori_tahap', 'survei')->isNotEmpty()
                || in_array($program->status, ['menunggu_review_survei', 'survei_disetujui', 'tertunda', 'pelaksanaan', 'kasbon_disetujui', 'kasbon_ditolak', 'menunggu_review_laporan_akhir', 'revisi_laporan_akhir', 'selesai'], true));
        $showStage3 = $program->exists
            && (filled($program->jalur_pengajuan)
                || in_array($program->status, ['survei_disetujui', 'tertunda', 'pelaksanaan', 'kasbon_disetujui', 'kasbon_ditolak', 'menunggu_review_laporan_akhir', 'revisi_laporan_akhir', 'selesai'], true));
        $isStage3Active = $program->status === 'survei_disetujui';
        $stage4Statuses = ['survei_disetujui', 'tertunda', 'pelaksanaan', 'kasbon_disetujui', 'kasbon_ditolak', 'menunggu_review_laporan_akhir', 'revisi_laporan_akhir', 'selesai'];
        $showStage4 = $program->exists
            && in_array($program->status, $stage4Statuses, true)
            && (filled($program->jalur_pengajuan)
                || in_array($program->status, ['kasbon_disetujui', 'kasbon_ditolak', 'menunggu_review_laporan_akhir', 'revisi_laporan_akhir', 'selesai'], true));
        $isStage4Active = $program->status === 'tertunda';
        $stage5Statuses = ['pelaksanaan', 'kasbon_disetujui', 'menunggu_review_laporan_akhir', 'revisi_laporan_akhir', 'selesai'];
        $showStage5 = $program->exists && in_array($program->status, $stage5Statuses, true);
        $isStage5Active = in_array($program->status, ['pelaksanaan', 'kasbon_disetujui', 'menunggu_review_laporan_akhir', 'revisi_laporan_akhir'], true);
        $money = fn ($value) => $value === null || $value === '' ? '' : number_format((float) $value, 0, ',', '.');
    @endphp

    <style>
        .program-workflow{max-width:1120px;margin:0 auto;color:#111827}
        .program-workflow h1{font-size:30px;font-weight:700;margin:0}
        .program-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px}
        .program-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:22px;margin:0 0 20px;box-shadow:0 2px 6px #0000000d}
        .program-section-title{font-size:19px;font-weight:700;margin:0 0 16px}
        .program-field{display:grid;grid-template-columns:260px minmax(0,1fr);align-items:start;gap:14px;margin:13px 0}
        .program-field>label,.program-field>span:first-child{padding-top:10px;font-weight:600}
        .program-workflow .category-row{grid-template-columns:260px minmax(0,1fr);align-items:start;gap:14px;margin:13px 0;padding:0}
        .program-workflow .category-row>span:first-child{padding-top:10px;color:#111827;font-size:16px;font-weight:600}
        .program-workflow .category-options{grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
        .program-workflow .category-options label{justify-content:flex-start;gap:6px;padding:0 8px;white-space:nowrap;font-size:14px}
        .program-workflow .category-options input{flex:0 0 auto;margin:0}
        .program-field input:not([type=radio]),.program-field textarea,.program-field select,.program-doc-row input:not([type=file]){width:100%;border:1px solid #cbd5e1;border-radius:6px;padding:10px 12px;background:#fff}
        .program-field textarea{min-height:96px}
        .program-types,.program-categories{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
        .program-types{grid-template-columns:repeat(2,minmax(0,1fr))}
        .program-choice{position:relative}
        .program-choice input{position:absolute;opacity:0}
        .program-choice span{display:block;border:1px solid #cbd5e1;border-radius:8px;padding:13px;text-align:center;font-weight:600;cursor:pointer;background:#fff}
        .program-choice input:checked+span{border-color:#2653ff;background:#eff6ff;color:#1d4ed8;box-shadow:0 0 0 2px #2653ff20}
        .program-doc-row{display:grid;grid-template-columns:245px minmax(180px,1fr) minmax(230px,1fr) auto;align-items:center;gap:12px;margin:10px 0}
        .program-file-picker{display:flex;align-items:center;gap:9px;min-width:0}
        .program-file-picker input[type=file]{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);clip-path:inset(50%);white-space:nowrap;border:0}
        .program-file-picker input[type=file]:focus-visible+label{outline:3px solid #93c5fd;outline-offset:2px}
        .program-file-picker label,.program-delete{border:0;border-radius:6px;background:#dc2626;color:#fff;padding:9px 13px;font-weight:600;white-space:nowrap;cursor:pointer}
        .program-filename{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#64748b}
        .program-file-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
        .program-file-actions a{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:6px 11px;border:1px solid #cbd5e1;border-radius:7px;background:#f8fafc;color:#1d4ed8;font-weight:600;font-size:13px;text-decoration:none}
        .program-file-actions a:hover{border-color:#93c5fd;background:#eff6ff}
        .program-delete{padding:7px 10px}
        .program-blue,.program-pink,.program-light{display:block;width:100%;border:0;border-radius:6px;padding:12px 16px;text-align:center;color:#fff;font-weight:700;text-decoration:none;cursor:pointer}
        .program-blue{background:#2653ff}.program-blue:hover{background:#1742e7}
        .program-pink{background:#d63384}.program-light{background:#7fa4e4}
        .program-actions{display:grid;gap:11px;margin:20px 0}
        .program-alert{padding:12px 15px;border-radius:7px;margin-bottom:15px}
        .program-alert.error{background:#fef2f2;color:#991b1b}.program-alert.success{background:#f0fdf4;color:#166534}
        .program-note{padding:13px;border-radius:7px;background:#fff7ed;color:#9a3412;margin:14px 0}
        .program-history{border-top:1px solid #e5e7eb;margin-top:22px;padding-top:18px}
        [x-cloak]{display:none!important}
        .stage-card{overflow:hidden;padding:0}
        .stage-card-toggle{display:flex;width:100%;align-items:center;justify-content:space-between;gap:14px;border:0;background:#fff;padding:18px 22px;text-align:left;cursor:pointer}
        .stage-card-toggle:hover{background:#f8fafc}
        .stage-card-heading,.stage-card-meta{display:flex;min-width:0;align-items:center;gap:12px}
        .stage-card-title{color:#0f172a;font-size:17px;font-weight:700}
        .stage-card-number{display:grid;width:34px;height:34px;flex:none;place-items:center;border-radius:10px;background:#e0e7ff;color:#3730a3;font-size:13px;font-weight:800}
        .stage-card-meta{flex:none}
        .stage-card-status{border-radius:999px;background:#f1f5f9;padding:5px 10px;color:#475569;font-size:12px;font-weight:600;white-space:nowrap}
        .stage-card-chevron{width:20px;height:20px;flex:none;color:#475569;transition:transform .2s ease}
        .stage-card-body{border-top:1px solid #e2e8f0;padding:20px 22px}
        .stage-edit-section{padding:16px 0;border-top:1px solid #e2e8f0}
        .stage-edit-section:first-of-type{border-top:0;padding-top:0}
        .stage3-route-options{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;align-items:stretch}
        .stage3-route-options .program-assistance-check{height:100%;align-items:flex-start;padding:14px}
        .stage3-route-options .program-assistance-check input{flex:0 0 18px;margin-top:2px}
        .stage3-route-options .program-assistance-check span{min-width:0;line-height:1.45}
        .stage3-status-field{display:flex;flex-direction:column;gap:8px;margin:18px 0}
        .stage3-status-field>span{padding:0!important;color:#334155;font-size:14px;font-weight:600}
        .stage3-status-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        .stage3-status-options .program-choice span{display:flex;min-height:48px;align-items:center;justify-content:center}
        @media(max-width:700px){.stage3-route-options{grid-template-columns:1fr}}
        @media(max-width:520px){.stage-card-toggle{align-items:flex-start;padding:16px}.stage-card-meta{gap:7px;flex-direction:column-reverse;align-items:flex-end}.stage-card-title{font-size:15px}.stage-card-body{padding:16px}}
        .program-timeline{position:relative;display:grid;gap:0;margin:0;padding:0;list-style:none}
        .program-timeline:before{position:absolute;top:10px;bottom:10px;left:8px;width:2px;background:#e2e8f0;content:""}
        .program-timeline-item{position:relative;display:grid;grid-template-columns:18px minmax(0,1fr);gap:12px;padding:0 0 16px}
        .program-timeline-item:last-child{padding-bottom:0}
        .program-timeline-dot{z-index:1;width:18px;height:18px;border:4px solid #dbeafe;border-radius:50%;background:#2563eb}
        .program-timeline-content{min-width:0;padding:0 0 2px}
        .program-timeline-heading{display:flex;align-items:baseline;justify-content:space-between;gap:12px}
        .program-timeline-heading time{flex:none;color:#64748b;font-size:12px}
        .program-timeline-meta,.program-timeline-note{margin-top:3px;color:#64748b;font-size:13px}
        .program-timeline-note{color:#475569}
        .program-review{display:grid;gap:10px;margin-top:14px}
        .program-review textarea{width:100%;min-height:80px;border:1px solid #cbd5e1;border-radius:6px;padding:10px}
        .program-review-buttons{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
        .program-review button{border:0;border-radius:6px;padding:11px;color:#fff;font-weight:700;cursor:pointer}
        .program-review .approve{background:#16a34a}.program-review .revise{background:#d97706}.program-review .reject{background:#dc2626}
        .program-readonly{color:#334155;white-space:pre-line}
        .program-status-indicator{display:inline-flex;border-radius:999px;background:#dcfce7;padding:8px 14px;color:#166534;font-weight:700}
        .program-hidden{display:none!important}
        .program-assistance-options{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
        .program-assistance-check{display:flex;align-items:center;gap:10px;border:1px solid #cbd5e1;border-radius:8px;padding:13px;font-weight:600;cursor:pointer}
        .program-assistance-check input{width:18px;height:18px;accent-color:#2653ff}
        .program-item-list{display:grid;gap:10px}
        .program-item-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(150px,.55fr) minmax(0,1fr) auto;gap:10px;align-items:center}
        .program-item-row input{width:100%;border:1px solid #cbd5e1;border-radius:6px;padding:10px 12px}
        .program-add-item{margin-top:10px;border:1px solid #2653ff;border-radius:6px;background:#eff6ff;padding:9px 13px;color:#1d4ed8;font-weight:600;cursor:pointer}
        .program-remove-item{border:0;border-radius:6px;background:#fee2e2;padding:9px 11px;color:#991b1b;font-weight:600;cursor:pointer}
        .program-subsection-title{margin:18px 0 8px;color:#334155;font-size:15px;font-weight:700}
        .program-date{max-width:320px}
        .verification-layout{display:grid;gap:14px}
        .verification-box{border:1px solid #dbe3ee;border-radius:12px;background:#f8fafc;padding:18px}
        .verification-box-header{display:flex;align-items:flex-start;gap:12px;margin-bottom:16px}
        .verification-box-number{display:grid;flex:0 0 30px;height:30px;place-items:center;border-radius:9px;background:#e0e7ff;color:#3730a3;font-size:13px;font-weight:800}
        .verification-box-title{margin:1px 0 3px;color:#0f172a;font-size:16px;font-weight:700}
        .verification-box-description{margin:0;color:#64748b;font-size:13px;line-height:1.5}
        .verification-fields-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .verification-fields-stack{display:flex;flex-direction:column;gap:20px}
        .verification-rka-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        .verification-pillar-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        .verification-rka-options .program-choice span,.verification-pillar-options .program-choice span{display:flex;min-height:48px;align-items:center;justify-content:center;padding:12px 16px;text-align:center;white-space:normal}
        .verification-fields-grid>.program-field{grid-template-columns:minmax(0,1fr);gap:6px;margin:0}
        .verification-fields-grid>.program-field>label,.verification-fields-grid>.program-field>span:first-child{padding-top:0}
        .verification-fields-grid>.program-field input,.verification-fields-grid>.program-field textarea,.verification-fields-grid>.program-field select{min-width:0}
        .verification-subcategory .program-field{display:flex;flex-direction:column;align-items:stretch;gap:6px;min-width:0;margin:0}
        .verification-subcategory .program-field>label,.verification-subcategory .program-field>span:first-child{padding-top:0;color:#334155;font-size:14px;font-weight:600}
        .verification-subcategory .program-field input:not([type=radio]),.verification-subcategory .program-field textarea,.verification-subcategory .program-field select{width:100%;min-width:0;border-radius:8px;padding:10px 14px;font-size:14px}
        .verification-full-width{grid-column:1/-1}
        .verification-box .program-types{gap:9px}
        .verification-box .program-choice span{height:100%;padding:11px 12px}
        .verification-cash-source-options{display:grid;grid-template-columns:1fr;gap:12px}
        .verification-cash-source-options .program-choice span{display:flex;min-height:48px;align-items:center;justify-content:center;text-align:center}
        .verification-box .program-assistance-options{gap:10px}
        .verification-box .program-assistance-check{min-height:48px;background:#fff;transition:border-color .15s,background .15s}
        .verification-assistance-options{display:grid;grid-template-columns:1fr;gap:12px}
        .verification-assistance-options .program-assistance-check{min-height:56px;padding:14px 16px}
        .verification-box .program-assistance-check:has(input:checked){border-color:#2563eb;background:#eff6ff;color:#1d4ed8}
        .verification-subcategory{display:flex;flex-direction:column;gap:16px;margin-top:16px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;padding:16px}
        .verification-subcategory-title{display:flex;align-items:center;gap:9px;margin:0;color:#1e293b;font-size:15px;font-weight:700}
        .verification-subcategory-title span{display:grid;width:24px;height:24px;place-items:center;border-radius:7px;background:#f1f5f9;color:#475569;font-size:12px}
        .verification-account-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:12px}
        .verification-vendor-heading{margin:8px 0 10px;color:#334155;font-size:14px;font-weight:700}
        .verification-vendor-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr)) auto;gap:10px;align-items:start}
        .verification-vendor-row .program-remove-item{align-self:center;justify-self:end}
        .program-info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        .program-info-grid .program-field{display:flex;flex-direction:column;align-items:stretch;gap:6px;margin:0;border:1px solid #e2e8f0;border-radius:9px;background:#f8fafc;padding:13px 15px}
        .program-info-grid .program-field>label,.program-info-grid .program-field>span:first-child{padding:0;color:#64748b;font-size:12px;font-weight:600}
        .program-info-grid .program-field strong,.program-info-grid .program-field .program-readonly{color:#1e293b;font-size:14px;font-weight:600}
        .program-info-wide{grid-column:1/-1}
        .verification-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        .verification-detail-grid .program-field{display:flex;flex-direction:column;gap:5px;margin:0;border:1px solid #e2e8f0;border-radius:9px;background:#f8fafc;padding:12px 14px}
        .verification-detail-grid .program-field>span:first-child{padding:0;color:#64748b;font-size:12px}
        .verification-detail-group{margin-top:18px}
        .verification-detail-group:first-of-type{margin-top:0}
        .verification-detail-title{margin:0 0 10px;color:#334155;font-size:15px;font-weight:700}
        .verification-detail-wide{grid-column:1/-1}
        .verification-detail-assistance{margin-top:14px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;padding:15px}
        .verification-detail-assistance-title{margin:0 0 8px;color:#1e293b;font-size:14px;font-weight:700}
        .verification-upload{background:#fff}
        .verification-upload .program-file-picker label{background:#2653ff}
        .verification-upload .program-doc-row{padding:10px 0;border-bottom:1px solid #e2e8f0}
        .verification-upload .program-doc-row:last-child{border-bottom:0}
        @media(min-width:640px){.verification-pillar-options{grid-template-columns:repeat(4,minmax(0,1fr))}.verification-assistance-options{grid-template-columns:repeat(3,minmax(0,1fr))}.verification-cash-source-options{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(min-width:768px){.program-info-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.verification-account-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.verification-detail-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(max-width:800px){.program-field,.program-workflow .category-row{grid-template-columns:1fr;gap:6px}.program-doc-row{grid-template-columns:1fr}.program-categories{grid-template-columns:repeat(2,minmax(0,1fr))}.program-workflow .category-options{grid-template-columns:repeat(2,minmax(0,1fr))}.program-file-actions{min-height:30px}.program-review-buttons{grid-template-columns:1fr}.program-heading{align-items:flex-start;flex-direction:column}.verification-fields-grid{grid-template-columns:1fr}.verification-full-width{grid-column:auto}.verification-box{padding:15px}.verification-vendor-row{grid-template-columns:1fr}}
        @media(max-width:700px){.program-assistance-options:not(.verification-assistance-options){grid-template-columns:1fr}}
        @media(max-width:639px){.verification-assistance-options{grid-template-columns:1fr}}
        @media(max-width:520px){.program-info-grid{grid-template-columns:1fr}.verification-detail-grid{grid-template-columns:1fr}.program-timeline-heading{align-items:flex-start;flex-direction:column;gap:2px}}
        @media(max-width:500px){.program-workflow .category-options{grid-template-columns:1fr}}
    </style>

    <main class="program-workflow">
        @include('admin.partials.status-badge-styles')
        <header class="program-heading">
            <h1>{{ $program->exists ? 'Detail Program TJSL INKA' : 'Buat Program Baru' }}</h1>
            @if($program->exists)
                @include('admin.partials.status-badge', ['status' => $program->status])
            @endif
        </header>

        @if(session('success'))<div class="program-alert success">{{ session('success') }}</div>@endif
        @if($errors->any())
            <div class="program-alert error"><strong>Periksa kembali isian:</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @if($program->catatan_terakhir)
            <div class="program-note"><strong>Catatan terakhir:</strong> {{ $program->catatan_terakhir }}</div>
        @endif

        @if(! $isSuperAdmin && $editable && (! $program->exists || in_array($program->status, ['draft', 'revisi'], true)))
            <form id="program-form" method="POST" enctype="multipart/form-data" action="{{ $formAction }}">
                @csrf
                @if($program->exists) @method('PUT') @endif
                <input type="hidden" name="jenis_kerjasama" id="jenis-kerjasama" value="{{ old('jenis_kerjasama', $program->jenis_kerjasama ?: 'pks') }}">

                <section id="stage-1" class="program-card stage-card" x-data="{ open: true }">
                    @include('admin.programs.partials.stage-accordion-header', [
                        'bodyId' => 'stage-1-edit-content',
                        'stageNumber' => '01',
                        'stageTitle' => $stageLabels['proposal'],
                        'stageStatus' => $program->exists ? ($statusLabels[$program->status] ?? 'Revisi') : 'Sedang Dikerjakan',
                    ])
                    <div id="stage-1-edit-content" class="stage-card-body" x-show="open" x-transition x-cloak>
                    <div class="stage-edit-section">
                    <h3 class="program-subsection-title">Jenis Kerja Sama &amp; Mitra</h3>
                    <div class="program-field">
                        <span>Tipe Kerja Sama</span>
                        <div class="program-types">
                            @foreach(['PKS' => 'pks', 'NON PKS' => 'non_pks'] as $label => $value)
                                <label class="program-choice">
                                    <input type="radio" name="tipe_kerjasama" value="{{ $label }}" @checked(old('tipe_kerjasama', $program->tipe_kerjasama ?: ($program->jenis_kerjasama === 'non_pks' ? 'NON PKS' : 'PKS')) === $label)>
                                    <span>{{ $label }}</span>
                                </label>
                                    @endforeach
                        </div>
                    </div>
                    <div class="program-field">
                        <span>Jenis Pengajuan</span>
                        <div class="program-types">
                            <label class="program-choice"><input type="radio" name="jenis_pengajuan" value="proposal_tjsl" @checked(old('jenis_pengajuan', $program->jenis_pengajuan ?: 'proposal_tjsl') === 'proposal_tjsl')><span>Proposal TJSL</span></label>
                            <label class="program-choice"><input type="radio" name="jenis_pengajuan" value="external_stakeholder" @checked(old('jenis_pengajuan', $program->jenis_pengajuan) === 'external_stakeholder')><span>External (Stakeholder)</span></label>
                        </div>
                    </div>
                    <div id="mitra-field" class="{{ old('jenis_pengajuan', $program->jenis_pengajuan) === 'external_stakeholder' ? '' : 'program-hidden' }}">
                        <div class="program-field">
                            <label for="nama-mitra">Nama Mitra <span aria-hidden="true">*</span></label>
                            <input id="nama-mitra" name="nama_mitra" value="{{ old('nama_mitra', $program->nama_mitra) }}" placeholder="Masukkan nama mitra" @required(old('jenis_pengajuan', $program->jenis_pengajuan) === 'external_stakeholder')>
                        </div>
                        <div class="program-field">
                            <label for="no-surat">Nomor Surat <span aria-hidden="true">*</span></label>
                            <input id="no-surat" name="no_surat" value="{{ old('no_surat', $program->no_surat) }}" placeholder="Masukkan nomor surat" @required(old('jenis_pengajuan', $program->jenis_pengajuan) === 'external_stakeholder')>
                        </div>
                        <div class="program-field">
                            <label for="tanggal-surat">Tanggal Surat <span aria-hidden="true">*</span></label>
                            <input id="tanggal-surat" class="program-date" type="date" name="tanggal_surat" value="{{ old('tanggal_surat', $program->tanggal_surat?->format('Y-m-d')) }}" @required(old('jenis_pengajuan', $program->jenis_pengajuan) === 'external_stakeholder')>
                        </div>
                    </div>
                    </div>

                <div class="stage-edit-section">
                    <h3 class="program-subsection-title">Informasi Utama Program</h3>
                    <div class="program-field">
                        <label for="nama_program">Nama Program <span aria-hidden="true">*</span></label>
                        <input id="nama_program" name="nama_program" required value="{{ old('nama_program', $program->nama_program) }}" placeholder="Masukkan nama program">
                    </div>
                    <div class="program-field">
                        <label for="deskripsi_program">Deskripsi Program <span aria-hidden="true">*</span></label>
                        <textarea id="deskripsi_program" name="deskripsi_program" required placeholder="Masukkan deskripsi program">{{ old('deskripsi_program', $program->deskripsi_program) }}</textarea>
                    </div>
                    <div class="program-field">
                        <label for="tanggal-program">Tanggal Program <span aria-hidden="true">*</span></label>
                        <input id="tanggal-program" class="program-date" type="date" name="tanggal_program" required value="{{ old('tanggal_program', $program->tanggal_program?->format('Y-m-d')) }}">
                    </div>
                    @include('admin.partials.pillar-selector', [
                        'pillars' => $pillars,
                        'selectedPillarId' => old('pillar_id', $program->pillar_id),
                        'readonly' => false,
                        'required' => true,
                        'fieldLabel' => 'Kategori Program / Pilar',
                    ])
                </div>

                <div class="stage-edit-section">
                    <h3 class="program-subsection-title">Permintaan Bantuan / Anggaran</h3>
                    <div class="program-assistance-options">
                        @foreach(['uang_tunai' => 'Uang Tunai', 'barang' => 'Barang', 'jasa' => 'Jasa'] as $type => $label)
                            <label class="program-assistance-check">
                                <input type="checkbox" name="jenis_bantuan[]" value="{{ $type }}" @checked(in_array($type, $selectedAssistance, true)) data-assistance-toggle="{{ $type }}">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="program-field {{ in_array('uang_tunai', $selectedAssistance, true) ? '' : 'program-hidden' }}" data-assistance-fields="uang_tunai">
                        <label for="nominal-uang-tunai">Jumlah Dana (Rp) <span aria-hidden="true">*</span></label>
                        <input id="nominal-uang-tunai" name="nominal_uang_tunai" inputmode="numeric" value="{{ old('nominal_uang_tunai', $money($program->nominal_uang_tunai)) }}" placeholder="Contoh: 10.000.000">
                    </div>
                    @foreach(['barang' => ['Barang', $barangItems], 'jasa' => ['Jasa', $jasaItems]] as $type => [$label, $items])
                        <div class="{{ in_array($type, $selectedAssistance, true) ? '' : 'program-hidden' }}" data-assistance-fields="{{ $type }}">
                            <h3 class="program-subsection-title">Rincian {{ $label }}</h3>
                            <div class="program-item-list" data-item-list="{{ $type }}">
                                @foreach($items as $index => $item)
                                    <div class="program-item-row">
                                        <input name="assistance_items[{{ $type }}][{{ $index }}][nama_item]" value="{{ $item['nama_item'] ?? '' }}" placeholder="{{ $type === 'barang' ? 'Nama Barang' : 'Jenis / Rincian Layanan Jasa' }}" aria-label="{{ $type === 'barang' ? 'Nama Barang' : 'Jenis Layanan Jasa' }}">
                                        @if($type === 'barang')
                                            <input name="assistance_items[barang][{{ $index }}][jumlah]" value="{{ $item['jumlah'] ?? '' }}" placeholder="Jumlah / Kuantitas">
                                            <input name="assistance_items[barang][{{ $index }}][keterangan]" value="{{ $item['keterangan'] ?? '' }}" placeholder="Keterangan (opsional)">
                                        @else
                                            <input type="hidden" name="assistance_items[jasa][{{ $index }}][jumlah]" value="">
                                            <input name="assistance_items[jasa][{{ $index }}][keterangan]" value="{{ $item['keterangan'] ?? '' }}" placeholder="Keterangan (opsional)">
                                        @endif
                                        <button class="program-remove-item" type="button" data-remove-item>Hapus</button>
                                    </div>
                                @endforeach
                            </div>
                            <button class="program-add-item" type="button" data-add-item="{{ $type }}">+ Tambah {{ $label }}</button>
                        </div>
                    @endforeach
                </div>

                <div class="stage-edit-section">
                    <h3 class="program-subsection-title">Kontak Person</h3>
                    <div class="program-field">
                        <label for="pic-nama">Nama Kontak Person</label>
                        <input id="pic-nama" name="pic_nama" value="{{ old('pic_nama', $program->pic_nama) }}" placeholder="Masukkan nama kontak person">
                    </div>
                    <div class="program-field">
                        <label for="pic-telepon">No. HP / WhatsApp</label>
                        <input id="pic-telepon" type="tel" name="pic_telepon" value="{{ old('pic_telepon', $program->pic_telepon) }}" placeholder="Contoh: 081234567890">
                    </div>
                </div>

                <div class="stage-edit-section">
                    <h3 class="program-subsection-title">Unggah Dokumen — Pengajuan Proposal Program</h3>
                    <div id="proposal-document-rows">
                        @php($proposal = $documents->firstWhere('document_type', 'proposal_pengajuan_program'))
                        <div class="program-doc-row">
                            <strong>Proposal Program <span aria-hidden="true">*</span></strong>
                            <input name="documents[proposal_pengajuan_program][nama]" value="{{ old('documents.proposal_pengajuan_program.nama', $proposal?->nama_dokumen) }}" placeholder="Masukkan nama dokumen">
                            <div class="program-file-picker">
                                <input id="proposal-file" type="file" name="documents[proposal_pengajuan_program][file]" @if(! $proposal) data-required-for-submit @endif>
                                <label for="proposal-file">Pilih File</label><span class="program-filename" data-file-name>{{ $proposal?->nama_file_asli ?: 'Belum ada file dipilih' }}</span>
                            </div>
                            <div class="program-file-actions">
                                @if($proposal)
                                    <a href="{{ route('admin.program-documents.view', $proposal) }}" target="_blank" rel="noopener">Lihat</a>
                                    <a href="{{ route('admin.program-documents.download', $proposal) }}">Unduh</a>
                                    @if($program->exists)<button class="program-delete" type="submit" form="delete-document-{{ $proposal->id }}">Hapus</button>@endif
                                @endif
                            </div>
                        </div>
                        @foreach($documents->where('kategori_tahap', 'proposal')->where('document_type', 'lainnya') as $document)
                            @include('admin.programs.partials.document-row', ['document' => $document, 'label' => $document->nama_dokumen, 'routePrefix' => 'admin', 'editable' => true])
                        @endforeach
                        <div class="program-doc-row">
                            <strong>Berkas Tambahan</strong>
                            <input name="documents[tambahan_awal][nama]" placeholder="Masukkan nama dokumen">
                            <div class="program-file-picker"><input id="proposal-extra-file" type="file" name="documents[tambahan_awal][file]"><label for="proposal-extra-file">Pilih File</label><span class="program-filename" data-file-name>Belum ada file dipilih</span></div>
                            <span></span>
                        </div>
                    </div>
                    <button type="button" class="program-blue" data-add-document="proposal-document-rows" data-field="documents" data-stage="proposal">Tambah Dokumen</button>
                </div>

                <div class="program-actions">
                    <a class="program-blue" href="{{ route('admin.programs.index') }}">Kembali ke Overview</a>
                    <button class="program-pink" name="action" value="draft">Simpan Draft</button>
                    <button class="program-blue" name="action" value="submit">Simpan dan Ajukan Kepada Super Admin</button>
                </div>
                    </div>
                </section>
            </form>
        @elseif($program->exists)
            <section id="stage-1" class="program-card stage-card" x-data="{ open: @js($isStage1Open) }">
                @include('admin.programs.partials.stage-accordion-header', [
                    'bodyId' => 'stage-1-detail-content',
                    'stageNumber' => '01',
                    'stageTitle' => $stageLabels['proposal'],
                    'stageStatus' => $statusLabels[$program->status] ?? 'Tahap 1',
                ])
                <div id="stage-1-detail-content" class="stage-card-body" x-show="open" x-transition x-cloak>
                <div class="program-info-grid">
                    <div class="program-field"><span>Tipe Kerja Sama</span><strong>{{ $program->cooperationLabel() }}</strong></div>
                    <div class="program-field"><span>Jenis Pengajuan</span><strong>{{ $program->jenis_pengajuan === 'external_stakeholder' ? 'External (Stakeholder)' : 'Proposal TJSL' }}</strong></div>
                    @if($program->jenis_pengajuan === 'external_stakeholder')
                        <div class="program-field"><span>Nama Mitra</span><strong>{{ $program->nama_mitra ?: '-' }}</strong></div>
                        <div class="program-field"><span>Nomor Surat</span><strong>{{ $program->no_surat ?: '-' }}</strong></div>
                        <div class="program-field"><span>Tanggal Surat</span><strong>{{ $program->tanggal_surat?->format('d/m/Y') ?? '-' }}</strong></div>
                    @endif
                    @foreach(['nama_program'=>'Nama Program','deskripsi_program'=>'Deskripsi Program'] as $name=>$label)
                        <div class="program-field {{ $name === 'deskripsi_program' ? 'program-info-wide' : '' }}"><span>{{ $label }}</span><div class="program-readonly">{{ $program->$name }}</div></div>
                    @endforeach
                    @if($program->tanggal_program)
                        <div class="program-field"><span>Tanggal Program</span><strong>{{ $program->tanggal_program->format('d/m/Y') }}</strong></div>
                    @endif
                    <div class="program-field"><span>Kategori Program / Pilar</span><strong>{{ $program->effective_pilar ?: 'Belum ditentukan' }}</strong></div>
                    @foreach(['sasaran_program'=>'Sasaran Program','lokasi_program'=>'Lokasi Program','tujuan_program'=>'Tujuan Program'] as $name=>$label)
                        @if(filled($name === 'tujuan_program' ? $storedGoalText : $program->$name) && (! in_array($name, ['sasaran_program', 'lokasi_program'], true) || ! filled($program->kebutuhan_program)))
                            <div class="program-field program-info-wide"><span>{{ $label }}</span><div class="program-readonly">{{ $name === 'tujuan_program' ? $storedGoalText : $program->$name }}</div></div>
                        @endif
                    @endforeach
                </div>
                <h3 class="program-subsection-title">Permintaan Bantuan</h3>
                @if(in_array('uang_tunai', $program->jenis_bantuan ?? [], true))
                    <div class="program-field"><span>Uang Tunai</span><strong>Rp {{ number_format((float) $program->nominal_uang_tunai, 0, ',', '.') }}</strong></div>
                @endif
                @php($barangDetails = $storedAssistanceItems->where('tipe', 'barang'))
                @if(in_array('barang', $program->jenis_bantuan ?? [], true) || $barangDetails->isNotEmpty())
                    <div class="program-field">
                        <span>Barang</span>
                        <div class="program-readonly">
                            @forelse($barangDetails as $item)
                                <div>{{ $item->nama_item }}{{ $item->jumlah ? ' — '.$item->jumlah : '' }}{{ $item->keterangan ? ' ('.$item->keterangan.')' : '' }}</div>
                            @empty
                                <span>-</span>
                            @endforelse
                        </div>
                    </div>
                @endif
                @php($jasaDetails = $storedAssistanceItems->where('tipe', 'jasa'))
                @if(in_array('jasa', $program->jenis_bantuan ?? [], true) || $jasaDetails->isNotEmpty() || filled($program->detail_jasa))
                    <div class="program-field">
                        <span>Jasa</span>
                        <div class="program-readonly">
                            @forelse($jasaDetails as $item)
                                <div>{{ $item->nama_item }}{{ $item->keterangan ? ' — '.$item->keterangan : '' }}</div>
                            @empty
                                {{ $program->detail_jasa ?: '-' }}
                            @endforelse
                        </div>
                    </div>
                @endif
                @if($program->realisasi_anggaran !== null)
                    <div class="program-field"><span>Realisasi Anggaran</span><strong>Rp {{ number_format((float) $program->realisasi_anggaran, 0, ',', '.') }}</strong></div>
                @endif
                <h3 class="program-subsection-title">Kontak Person</h3>
                <div class="program-field"><span>Nama Kontak Person</span><strong>{{ $program->pic_nama ?: '-' }}</strong></div>
                <div class="program-field"><span>No. HP / WhatsApp</span><strong>{{ $program->pic_telepon ?: '-' }}</strong></div>
                @include('admin.programs.partials.stage-documents', ['stage' => 'proposal', 'documentsTitle' => 'Proposal dan Berkas Tambahan'])
                @if($isSuperAdmin && $program->status === 'menunggu_review_proposal')
                    <div class="mt-5 border-t border-slate-200 pt-5">
                        <h3 class="program-subsection-title">Review Pengajuan Proposal Program</h3>
                        @include('admin.programs.partials.review-form', ['action' => route('superadmin.programs.review-proposal', $program), 'allowReject' => true])
                    </div>
                @endif
                </div>
            </section>
        @endif

        @if($hasStage2Summary)
            <section id="stage-2" class="program-card stage-card" x-data="{ open: @js(in_array($program->status, ['menunggu_review_survei', 'perbaikan'], true)) }">
                @include('admin.programs.partials.stage-accordion-header', [
                    'bodyId' => 'stage-2-detail-content',
                    'stageNumber' => '02',
                    'stageTitle' => $stageLabels['survei'],
                    'stageStatus' => $statusLabels[$program->status] ?? 'Verifikasi Program',
                ])
                <div id="stage-2-detail-content" class="stage-card-body" x-show="open" x-transition x-cloak>
            <h2 class="program-section-title">Hasil Verifikasi Program</h2>
            @if($program->kesesuaian_rka || $program->kebutuhan_program)
                <div class="verification-detail-content">
                    <section class="verification-detail-group">
                        <h3 class="verification-detail-title">01 Kesesuaian RKA &amp; Pilar Final</h3>
                        <div class="verification-detail-grid">
                            <div class="program-field"><span>Kesesuaian dengan RKA</span><strong>{{ $program->kesesuaian_rka ?: '-' }}</strong></div>
                            <div class="program-field"><span>Pilar Final Verifikasi</span><strong>{{ $program->effective_pilar ?: '-' }}</strong></div>
                        </div>
                    </section>
                    <section class="verification-detail-group">
                        <h3 class="verification-detail-title">02 Kategori TPB &amp; Sisa Anggaran</h3>
                        <div class="verification-detail-grid">
                            <div class="program-field"><span>Kategori Program (No. TPB)</span><strong>{{ $program->no_tpb ?: '-' }}</strong></div>
                            <div class="program-field"><span>Jenis Program (Dalam TPB)</span><strong>{{ $program->jenis_program_tpb ?: '-' }}</strong></div>
                            <div class="program-field"><span>Sisa Anggaran</span><strong>{{ $program->sisa_anggaran === null ? '-' : 'Rp '.number_format((float) $program->sisa_anggaran, 0, ',', '.') }}</strong></div>
                        </div>
                    </section>
                    <section class="verification-detail-group">
                        <h3 class="verification-detail-title">03 Hasil Verifikasi Lapangan</h3>
                        <div class="verification-detail-grid">
                            <div class="program-field verification-detail-wide"><span>Kebutuhan Program</span><div class="program-readonly">{{ $program->kebutuhan_program ?: '-' }}</div></div>
                            <div class="program-field"><span>Lokasi Program</span><strong>{{ $program->lokasi_program ?: '-' }}</strong></div>
                            <div class="program-field"><span>Sasaran Program</span><strong>{{ $program->sasaran_program ?: '-' }}</strong></div>
                            <div class="program-field"><span>Tanggal Pelaksanaan</span><strong>{{ $program->tanggal_pelaksanaan?->format('d/m/Y') ?? '-' }}</strong></div>
                            @if(filled($program->keterangan_verifikasi))
                                <div class="program-field verification-detail-wide"><span>Keterangan Verifikasi</span><div class="program-readonly">{{ $program->keterangan_verifikasi }}</div></div>
                            @endif
                        </div>
                    </section>
                    <section class="verification-detail-group">
                        <h3 class="verification-detail-title">04 Usulan Jenis Bantuan</h3>
                    @if(in_array('uang', $program->jenis_bantuan_verifikasi ?? [], true))
                        <div class="verification-detail-assistance">
                            <h4 class="verification-detail-assistance-title">Uang</h4>
                            <div>Sumber: {{ $program->sumber_pencairan === 'lainnya' ? $program->sumber_pencairan_lainnya : ($program->sumber_pencairan === 'kas_bon' ? 'Kasbon' : ucfirst(str_replace('_', ' ', $program->sumber_pencairan ?? '-'))) }}</div>
                            <div>Nama Pemilik Rekening: {{ $program->nama_pemilik_rekening ?: '-' }}</div>
                            <div>Nomor Rekening: {{ $program->nomor_rekening ?: '-' }}</div>
                            <div>Nama Bank: {{ $program->nama_bank ?: '-' }}</div>
                        </div>
                    @endif
                    @if(in_array('barang', $program->jenis_bantuan_verifikasi ?? [], true))
                        <div class="verification-detail-assistance">
                            <h4 class="verification-detail-assistance-title">Barang</h4>
                            <div>Jalur: {{ $program->jalur_pengadaan_barang === 'barang_bekas' ? 'Barang Bekas Tidak Terpakai / Sisa Produksi' : 'Proses Pengadaan' }}</div>
                            @if($program->jalur_pengadaan_barang === 'proses_pengadaan')
                                <div>Sub-jalur: {{ $program->sub_jalur_pengadaan === 'non_logistik' ? 'Non Logistik (Kasbon / Pembelian Barang Langsung)' : 'Logistik' }}</div>
                                @foreach($program->verificationVendors->where('tipe', 'barang') as $vendor)
                                    <div>{{ $vendor->nama_vendor }} — {{ $vendor->rincian_barang }}{{ $vendor->harga !== null ? ' — Rp '.number_format((float) $vendor->harga, 0, ',', '.') : '' }}</div>
                                @endforeach
                            @else
                                <div>Sumber Barang: {{ $program->sumber_barang ?: '-' }}</div>
                                <div>Deskripsi Barang: {{ $program->deskripsi_barang ?: '-' }}</div>
                            @endif
                        </div>
                    @endif
                    @if(in_array('jasa', $program->jenis_bantuan_verifikasi ?? [], true))
                        <div class="verification-detail-assistance">
                            <h4 class="verification-detail-assistance-title">Jasa</h4>
                            <div>Kategori: {{ $program->kategori_jasa === 'non_logistik' ? 'Non Logistik (Kasbon / Pembayaran Jasa Langsung)' : ucfirst(str_replace('_', ' ', $program->kategori_jasa ?? '-')) }}</div>
                            <div>Keterangan Jasa: {{ $program->keterangan_jasa_verifikasi ?: '-' }}</div>
                            @foreach($program->verificationVendors->where('tipe', 'jasa') as $vendor)
                                <div>{{ $vendor->nama_vendor }} — {{ $vendor->rincian_jasa }}{{ $vendor->harga !== null ? ' — Rp '.number_format((float) $vendor->harga, 0, ',', '.') : '' }}</div>
                            @endforeach
                        </div>
                    @endif
                    </section>
                </div>
            @endif
            @include('admin.programs.partials.stage-documents', ['stage' => 'survei', 'documentsTitle' => 'Kelengkapan Survei dan Berkas Tambahan'])
            @if($isSuperAdmin && $program->status === 'menunggu_review_survei')
                <div class="mt-5 border-t border-slate-200 pt-5">
                    <h3 class="program-subsection-title">Review Verifikasi Program</h3>
                    @include('admin.programs.partials.review-form', ['action' => route('superadmin.programs.review-survei', $program), 'allowReject' => true])
                </div>
            @endif
                </div>
            </section>
        @endif

            @if($isStage2FormActive)
                <section id="stage-2-edit" class="program-card stage-card" x-data="{ open: true }">
                    @include('admin.programs.partials.stage-accordion-header', [
                        'bodyId' => 'stage-2-edit-content',
                        'stageNumber' => '02',
                        'stageTitle' => $stageLabels['survei'],
                        'stageStatus' => $statusLabels[$program->status] ?? 'Sedang Dikerjakan',
                    ])
                    <div id="stage-2-edit-content" class="stage-card-body" x-show="open" x-transition x-cloak>
                    @php($survey = $documents->firstWhere('document_type', 'kelengkapan_survei'))
                    <form id="survey-form" method="POST" enctype="multipart/form-data" action="{{ route('admin.programs.tahap2.store', $program) }}">
                        @csrf
                        <div class="verification-layout">
                            <section class="verification-box">
                                <header class="verification-box-header">
                                    <span class="verification-box-number">01</span>
                                    <div>
                                        <h3 class="verification-box-title">Kesesuaian RKA &amp; Pilar Final</h3>
                                        <p class="verification-box-description">Tentukan kesesuaian anggaran dan pilar yang akan digunakan sebagai kategori akhir program.</p>
                                    </div>
                                </header>
                                <div class="verification-fields-stack flex flex-col gap-5">
                                    <div class="program-field">
                                        <span>Kesesuaian RKA <span aria-hidden="true">*</span></span>
                                        <div class="verification-rka-options grid grid-cols-2 gap-3">
                                            @foreach(['CID', 'NON CID'] as $rka)
                                                <label class="program-choice"><input type="radio" name="kesesuaian_rka" value="{{ $rka }}" required @checked(old('kesesuaian_rka', $program->kesesuaian_rka) === $rka)><span>{{ $rka }}</span></label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="program-field">
                                        <span>Pilihan Pilar (Final) <span aria-hidden="true">*</span></span>
                                        <div class="verification-pillar-options grid grid-cols-2 gap-3 sm:grid-cols-4">
                                            @foreach($pillars as $pillar)
                                                <label class="program-choice"><input type="radio" name="pilar_verifikasi" value="{{ $pillar->name }}" required @checked(old('pilar_verifikasi', $program->pilar_verifikasi ?: $program->kategori_program) === $pillar->name)><span>{{ $pillar->name }}</span></label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="verification-box">
                                <header class="verification-box-header">
                                    <span class="verification-box-number">02</span>
                                    <div>
                                        <h3 class="verification-box-title">Kategori TPB &amp; Sisa Anggaran</h3>
                                        <p class="verification-box-description">Lengkapi klasifikasi Tujuan Pembangunan Berkelanjutan dan informasi anggaran tersisa.</p>
                                    </div>
                                </header>
                                <div class="verification-fields-grid">
                                    <div class="program-field">
                                        <label for="no-tpb">Kategori Program (No. TPB)</label>
                                        <input id="no-tpb" name="no_tpb" value="{{ old('no_tpb', $program->no_tpb) }}" placeholder="Contoh: TPB 4 - Pendidikan Berkualitas">
                                    </div>
                                    <div class="program-field">
                                        <label for="jenis-program-tpb">Jenis Program (Dalam TPB)</label>
                                        <input id="jenis-program-tpb" name="jenis_program_tpb" value="{{ old('jenis_program_tpb', $program->jenis_program_tpb) }}" placeholder="Masukkan rincian sub-program">
                                    </div>
                                    <div class="program-field">
                                        <label for="sisa-anggaran">Sisa Anggaran (Rp)</label>
                                        <input id="sisa-anggaran" name="sisa_anggaran" inputmode="numeric" value="{{ old('sisa_anggaran', $money($program->sisa_anggaran)) }}" placeholder="Contoh: 10.000.000">
                                    </div>
                                </div>
                            </section>

                            <section class="verification-box">
                                <header class="verification-box-header">
                                    <span class="verification-box-number">03</span>
                                    <div>
                                        <h3 class="verification-box-title">Hasil Verifikasi Lapangan</h3>
                                        <p class="verification-box-description">Catat kebutuhan, lokasi, sasaran, dan tanggal pelaksanaan hasil survei lapangan.</p>
                                    </div>
                                </header>
                                <div class="verification-fields-grid">
                                    <div class="program-field verification-full-width">
                                        <label for="kebutuhan-program">Kebutuhan Program <span aria-hidden="true">*</span></label>
                                        <textarea id="kebutuhan-program" name="kebutuhan_program" required placeholder="Uraikan kebutuhan utama program">{{ old('kebutuhan_program', $program->kebutuhan_program) }}</textarea>
                                    </div>
                                    <div class="program-field">
                                        <label for="lokasi-verifikasi">Lokasi Program <span aria-hidden="true">*</span></label>
                                        <input id="lokasi-verifikasi" name="lokasi_program" required value="{{ old('lokasi_program', $program->lokasi_program) }}" placeholder="Masukkan lokasi program">
                                    </div>
                                    <div class="program-field">
                                        <label for="sasaran-verifikasi">Sasaran Program <span aria-hidden="true">*</span></label>
                                        <input id="sasaran-verifikasi" name="sasaran_program" required value="{{ old('sasaran_program', $program->sasaran_program) }}" placeholder="Masukkan sasaran program">
                                    </div>
                                    <div class="program-field">
                                        <label for="tanggal-pelaksanaan">Tanggal Pelaksanaan <span aria-hidden="true">*</span></label>
                                        <input id="tanggal-pelaksanaan" type="date" name="tanggal_pelaksanaan" required value="{{ old('tanggal_pelaksanaan', $program->tanggal_pelaksanaan?->format('Y-m-d')) }}">
                                    </div>
                                    <div class="program-field verification-full-width">
                                        <label for="keterangan-verifikasi">Keterangan <span class="font-normal text-slate-500">(opsional)</span></label>
                                        <textarea id="keterangan-verifikasi" name="keterangan_verifikasi" placeholder="Tambahkan catatan jika diperlukan">{{ old('keterangan_verifikasi', $program->keterangan_verifikasi) }}</textarea>
                                    </div>
                                </div>
                            </section>

                            <section class="verification-box">
                                <header class="verification-box-header">
                                    <span class="verification-box-number">04</span>
                                    <div>
                                        <h3 class="verification-box-title">Usulan Jenis Bantuan <span class="text-red-600">*</span></h3>
                                        <p class="verification-box-description">Pilih satu atau beberapa jenis bantuan. Rincian lanjutan muncul setelah jenis bantuan dipilih.</p>
                                    </div>
                                </header>
                                <div class="program-assistance-options verification-assistance-options grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    @foreach(['uang' => 'Uang', 'barang' => 'Barang', 'jasa' => 'Jasa'] as $type => $label)
                                        <label class="program-assistance-check">
                                            <input type="checkbox" name="jenis_bantuan_verifikasi[]" value="{{ $type }}" @checked(in_array($type, $selectedVerificationAssistance, true)) data-verification-toggle="{{ $type }}">
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="verification-subcategory mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 {{ in_array('uang', $selectedVerificationAssistance, true) ? '' : 'program-hidden' }}" data-verification-fields="uang">
                                    <h4 class="verification-subcategory-title"><span>U</span> Uang</h4>
                                    <div class="verification-fields-grid">
                                        <div class="program-field verification-full-width">
                                            <span>Sumber / Mekanisme Pencairan <span aria-hidden="true">*</span></span>
                                            <div class="verification-cash-source-options grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                @foreach(['kas_bon' => 'Kasbon', 'dana_sharing' => 'Dana Sharing', 'lainnya' => 'Lainnya'] as $value => $label)
                                                    <label class="program-choice"><input type="radio" name="sumber_pencairan" value="{{ $value }}" @checked(old('sumber_pencairan', $program->sumber_pencairan) === $value)><span>{{ $label }}</span></label>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="program-field verification-full-width mt-3 w-full {{ old('sumber_pencairan', $program->sumber_pencairan) === 'lainnya' ? '' : 'program-hidden' }}" data-cash-other>
                                            <label for="sumber-pencairan-lainnya">Keterangan Sumber <span aria-hidden="true">*</span></label>
                                            <input id="sumber-pencairan-lainnya" name="sumber_pencairan_lainnya" value="{{ old('sumber_pencairan_lainnya', $program->sumber_pencairan_lainnya) }}" placeholder="Tuliskan mekanisme pencairan">
                                        </div>
                                        <div class="verification-account-grid verification-full-width grid grid-cols-1 gap-3 md:grid-cols-3">
                                            <div class="program-field">
                                                <label class="text-sm font-semibold text-slate-700" for="nama-pemilik-rekening">Nama Pemilik Rekening <span aria-hidden="true">*</span></label>
                                                <input class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm" id="nama-pemilik-rekening" type="text" name="nama_pemilik_rekening" value="{{ old('nama_pemilik_rekening', $program->nama_pemilik_rekening) }}" placeholder="Sesuai buku rekening">
                                            </div>
                                            <div class="program-field">
                                                <label class="text-sm font-semibold text-slate-700" for="nomor-rekening">Nomor Rekening <span aria-hidden="true">*</span></label>
                                                <input class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm" id="nomor-rekening" type="text" name="nomor_rekening" value="{{ old('nomor_rekening', $program->nomor_rekening) }}" placeholder="Masukkan nomor rekening">
                                            </div>
                                            <div class="program-field">
                                                <label class="text-sm font-semibold text-slate-700" for="nama-bank">Nama Bank <span aria-hidden="true">*</span></label>
                                                <input class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm" id="nama-bank" type="text" name="nama_bank" value="{{ old('nama_bank', $program->nama_bank) }}" placeholder="Masukkan nama bank">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="verification-subcategory mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 {{ in_array('barang', $selectedVerificationAssistance, true) ? '' : 'program-hidden' }}" data-verification-fields="barang">
                                    <h4 class="verification-subcategory-title"><span>B</span> Barang</h4>
                                    <div class="verification-fields-grid">
                                        <div class="program-field verification-full-width">
                                            <span>Jalur Pengadaan <span aria-hidden="true">*</span></span>
                                            <div class="program-types">
                                                @foreach(['proses_pengadaan' => 'Proses Pengadaan', 'barang_bekas' => 'Barang Bekas Tidak Terpakai / Sisa Produksi'] as $value => $label)
                                                    <label class="program-choice"><input type="radio" name="jalur_pengadaan_barang" value="{{ $value }}" @checked(old('jalur_pengadaan_barang', $program->jalur_pengadaan_barang) === $value)><span>{{ $label }}</span></label>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="{{ old('jalur_pengadaan_barang', $program->jalur_pengadaan_barang) === 'proses_pengadaan' ? '' : 'program-hidden' }} verification-full-width" data-procurement-fields>
                                            <div class="program-field">
                                                <span>Sub-jalur <span aria-hidden="true">*</span></span>
                                                <div class="program-types">
                                                    <label class="program-choice"><input type="radio" name="sub_jalur_pengadaan" value="logistik" @checked(old('sub_jalur_pengadaan', $program->sub_jalur_pengadaan) === 'logistik')><span>Logistik</span></label>
                                                    <label class="program-choice"><input type="radio" name="sub_jalur_pengadaan" value="non_logistik" @checked(old('sub_jalur_pengadaan', $program->sub_jalur_pengadaan) === 'non_logistik')><span>Non Logistik (Kasbon / Pembelian Barang Langsung)</span></label>
                                                </div>
                                            </div>
                                            <div class="{{ old('sub_jalur_pengadaan', $program->sub_jalur_pengadaan) === 'non_logistik' ? '' : 'program-hidden' }}" data-goods-vendors>
                                            <h5 class="verification-vendor-heading">Usulan Vendor</h5>
                                            <div class="program-item-list" data-vendor-list>
                                                @foreach($vendorRows as $index => $vendor)
                                                    <div class="program-item-row verification-vendor-row">
                                                        <div class="program-field">
                                                            <label for="vendor-{{ $index }}-nama">Nama Vendor <span aria-hidden="true">*</span></label>
                                                            <input id="vendor-{{ $index }}-nama" name="vendors[{{ $index }}][nama_vendor]" value="{{ $vendor['nama_vendor'] ?? '' }}" placeholder="Nama Vendor">
                                                        </div>
                                                        <div class="program-field">
                                                            <label for="vendor-{{ $index }}-rincian">Rincian Barang <span aria-hidden="true">*</span></label>
                                                            <input id="vendor-{{ $index }}-rincian" name="vendors[{{ $index }}][rincian_barang]" value="{{ $vendor['rincian_barang'] ?? '' }}" placeholder="Rincian Barang">
                                                        </div>
                                                        <div class="program-field">
                                                            <label for="vendor-{{ $index }}-harga">Harga (Rp) <span aria-hidden="true">*</span></label>
                                                            <input id="vendor-{{ $index }}-harga" name="vendors[{{ $index }}][harga]" inputmode="numeric" value="{{ $vendor['harga'] ?? '' }}" placeholder="Masukkan harga">
                                                        </div>
                                                        <button class="program-remove-item" type="button" data-remove-item>Hapus</button>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <button class="program-add-item" type="button" data-add-vendor>+ Tambah Vendor/Barang</button>
                                            </div>
                                        </div>
                                        <div class="{{ old('jalur_pengadaan_barang', $program->jalur_pengadaan_barang) === 'barang_bekas' ? '' : 'program-hidden' }} verification-fields-grid verification-full-width" data-used-goods-fields>
                                            <div class="program-field">
                                                <label for="sumber-barang">Sumber Barang <span aria-hidden="true">*</span></label>
                                                <input id="sumber-barang" name="sumber_barang" value="{{ old('sumber_barang', $program->sumber_barang) }}" placeholder="Masukkan sumber barang">
                                            </div>
                                            <div class="program-field">
                                                <label for="deskripsi-barang">Deskripsi Barang <span aria-hidden="true">*</span></label>
                                                <textarea id="deskripsi-barang" name="deskripsi_barang" placeholder="Jelaskan kondisi dan rincian barang">{{ old('deskripsi_barang', $program->deskripsi_barang) }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="verification-subcategory mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 {{ in_array('jasa', $selectedVerificationAssistance, true) ? '' : 'program-hidden' }}" data-verification-fields="jasa">
                                    <h4 class="verification-subcategory-title"><span>J</span> Jasa</h4>
                                    <div class="verification-fields-grid">
                                        <div class="program-field">
                                            <label for="kategori-jasa">Kategori Jasa <span aria-hidden="true">*</span></label>
                                            <select id="kategori-jasa" name="kategori_jasa">
                                                <option value="">Pilih kategori</option>
                                                @foreach(['logistik' => 'Logistik', 'non_logistik' => 'Non Logistik (Kasbon / Pembayaran Jasa Langsung)', 'lainnya' => 'Lainnya'] as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('kategori_jasa', $program->kategori_jasa) === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="program-field verification-full-width {{ old('kategori_jasa', $program->kategori_jasa) ? '' : 'program-hidden' }}" data-service-description>
                                            <label for="keterangan-jasa-verifikasi">Keterangan Jasa <span aria-hidden="true">*</span></label>
                                            <input id="keterangan-jasa-verifikasi" name="keterangan_jasa_verifikasi" value="{{ old('keterangan_jasa_verifikasi', $program->keterangan_jasa_verifikasi) }}" placeholder="Tuliskan keterangan jasa">
                                        </div>
                                        <div class="verification-full-width {{ old('kategori_jasa', $program->kategori_jasa) === 'non_logistik' ? '' : 'program-hidden' }}" data-service-vendors>
                                            <h5 class="verification-vendor-heading">Usulan Vendor Jasa</h5>
                                            <div class="program-item-list" data-service-vendor-list>
                                                @foreach($serviceVendorRows as $index => $vendor)
                                                    <div class="program-item-row verification-vendor-row">
                                                        <div class="program-field">
                                                            <label for="service-vendor-{{ $index }}-nama">Nama Vendor <span aria-hidden="true">*</span></label>
                                                            <input id="service-vendor-{{ $index }}-nama" name="service_vendors[{{ $index }}][nama_vendor]" value="{{ $vendor['nama_vendor'] ?? '' }}" placeholder="Nama Vendor">
                                                        </div>
                                                        <div class="program-field">
                                                            <label for="service-vendor-{{ $index }}-rincian">Rincian Jasa <span aria-hidden="true">*</span></label>
                                                            <input id="service-vendor-{{ $index }}-rincian" name="service_vendors[{{ $index }}][rincian_jasa]" value="{{ $vendor['rincian_jasa'] ?? '' }}" placeholder="Rincian Jasa">
                                                        </div>
                                                        <div class="program-field">
                                                            <label for="service-vendor-{{ $index }}-harga">Harga (Rp) <span aria-hidden="true">*</span></label>
                                                            <input id="service-vendor-{{ $index }}-harga" name="service_vendors[{{ $index }}][harga]" inputmode="numeric" value="{{ $vendor['harga'] ?? '' }}" placeholder="Masukkan harga">
                                                        </div>
                                                        <button class="program-remove-item" type="button" data-remove-item>Hapus</button>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <button class="program-add-item" type="button" data-add-service-vendor>+ Tambah Vendor/Jasa</button>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="verification-box verification-upload">
                                <header class="verification-box-header">
                                    <span class="verification-box-number">05</span>
                                    <div>
                                        <h3 class="verification-box-title">Kelengkapan Survei</h3>
                                        <p class="verification-box-description">Unggah berkas utama dan dokumen tambahan. Setiap file maksimal 10 MB.</p>
                                    </div>
                                </header>
                                <div id="survey-document-rows">
                                    <div class="program-doc-row">
                                        <strong>Kelengkapan Survei</strong>
                                        <input name="survey_documents[kelengkapan_survei][nama]" value="{{ old('survey_documents.kelengkapan_survei.nama', $survey?->nama_dokumen) }}" placeholder="Masukkan nama dokumen">
                                        <div class="program-file-picker">
                                            <input id="survey-file" type="file" name="survey_documents[kelengkapan_survei][file]" @if(! $survey) required @endif>
                                            <label for="survey-file">Pilih File</label>
                                            <span class="program-filename" data-file-name>{{ $survey?->nama_file_asli ?: 'Belum ada file dipilih' }}</span>
                                        </div>
                                        <div class="program-file-actions">
                                            @if($survey)
                                                <a href="{{ route('admin.program-documents.download', $survey) }}">Unduh</a>
                                                <button class="program-delete" type="submit" form="delete-document-{{ $survey->id }}">Hapus</button>
                                            @endif
                                        </div>
                                    </div>
                                    @foreach($documents->where('kategori_tahap', 'survei')->where('document_type', 'lainnya') as $document)
                                        @include('admin.programs.partials.document-row', ['document' => $document, 'label' => $document->nama_dokumen, 'routePrefix' => 'admin', 'editable' => true])
                                    @endforeach
                                    @foreach($documents->where('kategori_tahap', 'survei')->where('document_type', 'dokumentasi_verifikasi') as $document)
                                        @include('admin.programs.partials.document-row', ['document' => $document, 'label' => 'Dokumentasi', 'routePrefix' => 'admin', 'editable' => true])
                                    @endforeach
                                    <div class="program-doc-row">
                                        <strong>Berkas Tambahan</strong>
                                        <input name="survey_documents[tambahan_awal][nama]" placeholder="Masukkan nama dokumen">
                                        <div class="program-file-picker">
                                            <input id="survey-extra-file" type="file" name="survey_documents[tambahan_awal][file]">
                                            <label for="survey-extra-file">Pilih File</label>
                                            <span class="program-filename" data-file-name>Belum ada file dipilih</span>
                                        </div>
                                        <span></span>
                                    </div>
                                </div>
                                <button type="button" class="program-add-item" data-add-document="survey-document-rows" data-field="survey_documents" data-stage="survei">+ Tambah Dokumen</button>
                                <div class="mt-5 border-t border-slate-200 pt-5">
                                    <h3 class="program-subsection-title">Dokumentasi</h3>
                                    <p class="mb-3 text-sm text-slate-600">Unggah satu atau beberapa foto atau dokumen pendukung. Maksimal 10 MB per file.</p>
                                    <div class="program-file-picker">
                                        <input id="phase2-documentation" type="file" name="phase2_documentation[]" multiple>
                                        <label for="phase2-documentation">Pilih Dokumentasi</label>
                                        <span class="program-filename" data-phase2-file-names>Belum ada file dipilih</span>
                                    </div>
                                    <div id="phase2-documentation-preview" class="mt-3 space-y-2"></div>
                                </div>
                            </section>
                        </div>
                        <button class="program-blue" style="margin-top:12px">Ajukan Verifikasi Program</button>
                    </form>
                    </div>
                </section>
            @endif

            @if(in_array($program->status, ['approved_fase1', 'pending_fase2', 'completed'], true))
                <section class="program-card">
                    <h2 class="program-section-title">Proses BAST Program Lama</h2>
                    @php($bast = $documents->firstWhere('document_type', 'bast'))
                    @if($program->status === 'approved_fase1')
                        <p>{{ $program->fase2_rejected_reason ? 'BAST ditolak. Admin dapat mengunggah ulang dokumen.' : 'Menunggu Admin mengunggah dokumen BAST.' }}</p>
                    @elseif($bast)
                        <div class="program-doc-row"><strong>{{ $bast->nama_dokumen }}</strong><span>{{ $bast->nama_file_asli }}</span><div class="program-file-actions"><a href="{{ route($routePrefix.'.program-documents.download', $bast) }}">Unduh BAST</a></div></div>
                    @endif
                    @if(! $isSuperAdmin && in_array($program->status, ['approved_fase1', 'pending_fase2', 'completed'], true))
                        <a class="program-blue" href="{{ route('admin.programs.phase2', $program) }}">BAST &amp; Dokumentasi Tambahan</a>
                    @endif
                </section>
            @endif

            @if($showStage3)
                <section id="stage-3" class="program-card stage-card" x-data="{ open: @js($isStage3Active) }">
                    @include('admin.programs.partials.stage-accordion-header', [
                        'bodyId' => 'stage-3-content',
                        'stageNumber' => '03',
                        'stageTitle' => 'Tahap 3 — Tahap Pengajuan',
                        'stageStatus' => 'Pilihan Jalur Pengajuan',
                    ])
                    <div id="stage-3-content" class="stage-card-body" x-show="open" x-transition x-cloak>
                    <p class="mb-4 text-sm text-slate-600">Pilih satu atau beberapa jalur pengajuan yang akan ditempuh.</p>
                    @if(! $isSuperAdmin && in_array($program->status, ['survei_disetujui', 'tertunda', 'pelaksanaan'], true))
                    <form id="pengajuan-form" method="POST" action="{{ route('admin.programs.pengajuan.update', $program) }}">
                        @csrf
                        <div class="program-assistance-options stage3-route-options items-stretch grid grid-cols-1 gap-3 sm:grid-cols-3">
                            @foreach(['uang_tunai_kas_bon' => 'Kasbon (Uang Tunai)', 'barang_pengadaan_logistik' => 'Pengadaan Barang Logistik (Barang)', 'barang_bekas_jasa' => 'Permintaan Barang Bekas Tidak Terpakai / Jasa'] as $value => $label)
                                <label class="program-assistance-check">
                                    <input type="checkbox" name="jalur_pengajuan[]" value="{{ $value }}" @checked(in_array($value, $selectedRoutes, true))>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <button class="program-blue" style="margin-top:12px">Simpan Tahap Pengajuan</button>
                    </form>
                    @else
                        <div class="program-readonly">
                            @php($routeLabels = ['uang_tunai_kas_bon' => 'Kasbon (Uang Tunai)', 'barang_pengadaan_logistik' => 'Pengadaan Barang Logistik (Barang)', 'barang_bekas_jasa' => 'Permintaan Barang Bekas Tidak Terpakai / Jasa'])
                            @forelse($program->jalur_pengajuan ?? [] as $route)
                                <div>{{ $routeLabels[$route] ?? $route }}</div>
                            @empty
                                <span>Belum ada jalur pengajuan yang dipilih.</span>
                            @endforelse
                        </div>
                    @endif
                    </div>
                </section>
            @endif

            @if($showStage4)
                <section id="stage-4" class="program-card stage-card" x-data="{ open: @js($isStage4Active) }">
                    @include('admin.programs.partials.stage-accordion-header', [
                        'bodyId' => 'stage-4-content',
                        'stageNumber' => '04',
                        'stageTitle' => 'Tahap 4 — Pelaksanaan Program',
                        'stageStatus' => $statusLabels[$program->status] ?? 'Menunggu Pelaksanaan',
                    ])
                    <div id="stage-4-content" class="stage-card-body" x-show="open" x-transition x-cloak>
                    @if($program->status === 'tertunda' && filled($program->catatan_operasional))
                        <div class="program-field"><span>Catatan Penundaan</span><div class="program-readonly">{{ $program->catatan_operasional }}</div></div>
                    @endif
                    @if(! $isSuperAdmin && in_array($program->status, ['survei_disetujui', 'tertunda', 'pelaksanaan'], true))
                        <form id="pelaksanaan-form" method="POST" action="{{ route('admin.programs.pelaksanaan.update', $program) }}">
                            @csrf
                            <div class="program-types">
                                @foreach(['tertunda' => 'Tertunda', 'pelaksanaan' => 'Pelaksanaan'] as $value => $label)
                                    <label class="program-choice"><input type="radio" name="status_operasional" value="{{ $value }}" required @checked(old('status_operasional', $program->status_operasional ?: (in_array($program->status, ['tertunda', 'pelaksanaan'], true) ? $program->status : '')) === $value)><span>{{ $label }}</span></label>
                                @endforeach
                            </div>
                            <div class="program-field {{ old('status_operasional', $program->status_operasional ?: $program->status) === 'tertunda' ? '' : 'program-hidden' }}" data-operational-note>
                                <label for="catatan-operasional">Catatan Kendala / Alasan Penundaan <span class="font-normal text-slate-500">(opsional)</span></label>
                                <textarea id="catatan-operasional" name="catatan_operasional" placeholder="Jelaskan kendala jika diperlukan">{{ old('catatan_operasional', $program->catatan_operasional) }}</textarea>
                            </div>
                            <button class="program-blue" style="margin-top:12px">Simpan Status Pelaksanaan</button>
                        </form>
                    @else
                        <div class="program-field"><span>Status Pelaksanaan</span><strong>{{ $statusLabels[$program->status] ?? ucfirst(str_replace('_', ' ', $program->status)) }}</strong></div>
                    @endif
                    </div>
                </section>
            @endif

            @if($showStage5)
                <section id="stage-5" class="program-card stage-card" x-data="{ open: @js($isStage5Active) }">
                    @include('admin.programs.partials.stage-accordion-header', [
                        'bodyId' => 'stage-5-content',
                        'stageNumber' => '05',
                        'stageTitle' => $stageLabels['laporan_akhir'],
                        'stageStatus' => $statusLabels[$program->status] ?? 'Pelaporan Akhir',
                    ])
                    <div id="stage-5-content" class="stage-card-body" x-show="open" x-transition x-cloak>
                    @if($program->nama_program_laporan || $program->no_dokumen_laporan || $program->nilai_program_realisasi !== null)
                        <div class="verification-detail-grid mb-5">
                            <div class="program-field"><span>Nama Program</span><strong>{{ $program->nama_program_laporan ?: $program->nama_program }}</strong></div>
                            <div class="program-field"><span>Nilai Program (Realisasi)</span><strong>{{ $program->nilai_program_realisasi === null ? '-' : 'Rp '.number_format((float) $program->nilai_program_realisasi, 0, ',', '.') }}</strong></div>
                            <div class="program-field"><span>Nomor Dokumen</span><strong>{{ $program->no_dokumen_laporan ?: '-' }}</strong></div>
                            <div class="program-field"><span>Nama Dokumen</span><strong>{{ $program->nama_dokumen_laporan ?: '-' }}</strong></div>
                        </div>
                    @endif
                    @if(! $isSuperAdmin && in_array($program->status, ['pelaksanaan', 'kasbon_disetujui', 'revisi_laporan_akhir'], true))
                    @php($report = $documents->firstWhere('document_type', 'laporan_akhir'))
                    @php($documentation = $documents->firstWhere('document_type', 'dokumentasi_kegiatan'))
                    @php($activityPhotos = $documents->where('document_type', 'foto_kegiatan'))
                    <form id="final-report-form" method="POST" enctype="multipart/form-data" action="{{ route('admin.programs.laporan-akhir.store', $program) }}">
                        @csrf
                        <div class="program-field"><label for="nama-program-laporan">Nama Program <span aria-hidden="true">*</span></label><input id="nama-program-laporan" name="nama_program_laporan" required value="{{ old('nama_program_laporan', $program->nama_program_laporan ?: $program->nama_program) }}"></div>
                        <div class="program-field"><label for="nilai-program-realisasi">Nilai Program (Realisasi) (Rp) <span aria-hidden="true">*</span></label><input id="nilai-program-realisasi" name="nilai_program_realisasi" inputmode="numeric" required value="{{ old('nilai_program_realisasi', $money($program->nilai_program_realisasi ?? $program->realisasi_anggaran)) }}"></div>
                        <div class="program-field"><label for="no-dokumen-laporan">Nomor Dokumen <span aria-hidden="true">*</span></label><input id="no-dokumen-laporan" name="no_dokumen_laporan" required value="{{ old('no_dokumen_laporan', $program->no_dokumen_laporan) }}"></div>
                        <div class="program-field"><label for="nama-dokumen-laporan">Nama Dokumen <span aria-hidden="true">*</span></label><input id="nama-dokumen-laporan" name="nama_dokumen_laporan" required value="{{ old('nama_dokumen_laporan', $program->nama_dokumen_laporan) }}"></div>
                        <div id="final-document-rows">
                            <div class="program-doc-row"><strong>Dokumen Realisasi</strong><input name="report_documents[laporan_akhir][nama]" value="{{ old('report_documents.laporan_akhir.nama', $report?->nama_dokumen) }}" placeholder="Masukkan nama dokumen"><div class="program-file-picker"><input id="report-file" type="file" name="report_documents[laporan_akhir][file]" @if(! $report) required @endif><label for="report-file">Pilih File</label><span class="program-filename" data-file-name>{{ $report?->nama_file_asli ?: 'Belum ada file dipilih' }}</span></div><div class="program-file-actions">@if($report)<a href="{{ route('admin.program-documents.download', $report) }}">Unduh</a><button class="program-delete" type="submit" form="delete-document-{{ $report->id }}">Hapus</button>@endif</div></div>
                            @foreach($documents->where('kategori_tahap', 'laporan_akhir')->where('document_type', 'lainnya') as $document)
                                @include('admin.programs.partials.document-row', ['document' => $document, 'label' => $document->nama_dokumen, 'routePrefix' => 'admin', 'editable' => true])
                            @endforeach
                            <div class="program-doc-row"><strong>Berkas Tambahan</strong><input name="report_documents[tambahan_awal][nama]" placeholder="Masukkan nama dokumen"><div class="program-file-picker"><input id="report-extra-file" type="file" name="report_documents[tambahan_awal][file]"><label for="report-extra-file">Pilih File</label><span class="program-filename" data-file-name>Belum ada file dipilih</span></div><span></span></div>
                        </div>
                        <button type="button" class="program-blue" data-add-document="final-document-rows" data-field="report_documents" data-stage="laporan_akhir">Tambah Dokumen</button>
                        <div id="activity-document-rows" style="margin-top:18px">
                            <div class="program-doc-row"><strong>Dokumen Laporan Kegiatan</strong><input name="documentation_documents[dokumentasi_kegiatan][nama]" value="{{ old('documentation_documents.dokumentasi_kegiatan.nama', $documentation?->nama_dokumen) }}" placeholder="Masukkan nama dokumen"><div class="program-file-picker"><input id="activity-file" type="file" name="documentation_documents[dokumentasi_kegiatan][file]" @if(! $documentation) required @endif><label for="activity-file">Pilih File</label><span class="program-filename" data-file-name>{{ $documentation?->nama_file_asli ?: 'Belum ada file dipilih' }}</span></div><div class="program-file-actions">@if($documentation)<a href="{{ route('admin.program-documents.download', $documentation) }}">Unduh</a><button class="program-delete" type="submit" form="delete-document-{{ $documentation->id }}">Hapus</button>@endif</div></div>
                            @foreach($documents->where('kategori_tahap', 'dokumentasi')->where('document_type', 'lainnya') as $document)
                                @include('admin.programs.partials.document-row', ['document' => $document, 'label' => $document->nama_dokumen, 'routePrefix' => 'admin', 'editable' => true])
                            @endforeach
                            <div class="program-doc-row"><strong>Dokumentasi Tambahan</strong><input name="documentation_documents[tambahan_awal][nama]" placeholder="Masukkan keterangan/nama dokumentasi"><div class="program-file-picker"><input id="activity-extra-file" type="file" name="documentation_documents[tambahan_awal][file]"><label for="activity-extra-file">Pilih File</label><span class="program-filename" data-file-name>Belum ada file dipilih</span></div><span></span></div>
                        </div>
                        <button type="button" class="program-blue" data-add-document="activity-document-rows" data-field="documentation_documents" data-stage="dokumentasi" style="margin-top:10px">Tambah Dokumen</button>
                        <div class="mt-5">
                            <h3 class="program-subsection-title">Foto Kegiatan</h3>
                            @foreach($activityPhotos as $photo)
                                @php($photoExtension = strtolower(pathinfo($photo->file_path, PATHINFO_EXTENSION)))
                                <div class="program-doc-row">
                                    <strong>{{ $photo->nama_dokumen }}</strong>
                                    @if(in_array($photoExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true))
                                        <a href="{{ route('admin.program-documents.view', $photo) }}" target="_blank" rel="noopener"><img src="{{ route('admin.program-documents.view', $photo) }}" alt="{{ $photo->nama_file_asli }}" class="h-24 w-32 rounded object-cover"></a>
                                    @else
                                        <span class="program-readonly">{{ $photo->nama_file_asli }}</span>
                                    @endif
                                    <div class="program-file-actions"><a href="{{ route('admin.program-documents.download', $photo) }}">Unduh</a><button class="program-delete" type="submit" form="delete-document-{{ $photo->id }}">Hapus</button></div>
                                </div>
                            @endforeach
                            <div id="activity-photo-rows">
                                <div class="program-doc-row">
                                    <strong>Unggah foto</strong><span></span>
                                    <div class="program-file-picker"><input id="activity-photo-file" type="file" name="activity_photos[]" accept="image/*" multiple><label for="activity-photo-file">Pilih Foto</label><span class="program-filename" data-file-name>Belum ada file dipilih</span></div>
                                    <span></span>
                                </div>
                            </div>
                            <div id="activity-photo-preview" class="mt-3 flex flex-wrap gap-3"></div>
                            <button type="button" class="program-add-item" data-add-photo>+ Tambah Foto</button>
                        </div>
                        <button class="program-blue" style="margin-top:12px">Simpan dan Ajukan Laporan Akhir</button>
                    </form>
                    @else
                        @include('admin.programs.partials.stage-documents', ['stage' => 'laporan_akhir', 'documentsTitle' => 'Dokumen Pelaporan Akhir'])
                        @include('admin.programs.partials.stage-documents', ['stage' => 'dokumentasi', 'documentsTitle' => 'Dokumen Laporan Kegiatan dan Foto'])
                    @endif
                    @if($isSuperAdmin && in_array($program->status, ['pelaksanaan', 'kasbon_disetujui'], true) && $documents->whereIn('document_type', ['laporan_akhir', 'dokumentasi_kegiatan', 'foto_kegiatan'])->isEmpty())
                        <p class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">Menunggu Admin mengunggah dokumen pelaporan akhir.</p>
                    @endif
                    @if($isSuperAdmin && $program->status === 'menunggu_review_laporan_akhir')
                        <div class="mt-5 border-t border-slate-200 pt-5">
                            <h3 class="program-subsection-title">Review Laporan Akhir</h3>
                            @include('admin.programs.partials.review-form', ['action' => route('superadmin.programs.review-laporan-akhir', $program), 'allowReject' => false])
                        </div>
                    @endif
                    </div>
                </section>
            @endif

            @if($isSuperAdmin && in_array($program->status, ['pending_fase1', 'pending_fase2'], true))
                <section class="program-card">
                    <h2 class="program-section-title">{{ $program->status === 'pending_fase1' ? 'Review Proposal (Alur Lama)' : 'Review BAST (Alur Lama)' }}</h2>
                    <div class="program-review">
                        <form method="POST" action="{{ route('superadmin.programs.reject', $program) }}">@csrf<textarea name="rejected_reason" required minlength="5" placeholder="Catatan penolakan wajib diisi"></textarea><button class="reject">Ditolak</button></form>
                        <form method="POST" action="{{ route('superadmin.programs.approve', $program) }}">@csrf<button class="approve">Disetujui</button></form>
                    </div>
                </section>
            @endif

            @if($program->relationLoaded('logs') && $program->logs->isNotEmpty())
                <section class="program-card program-history">
                    <h2 class="program-section-title">Riwayat Perubahan Status</h2>
                    <ol class="program-timeline">
                        @foreach($program->logs as $log)
                            <li class="program-timeline-item">
                                <span class="program-timeline-dot" aria-hidden="true"></span>
                                <div class="program-timeline-content">
                                    <div class="program-timeline-heading">
                                        <strong>{{ $statusLabels[$log->to_status] ?? str_replace('_', ' ', ucfirst($log->to_status)) }}</strong>
                                        <time>{{ $log->created_at?->format('d/m/Y H:i') }}</time>
                                    </div>
                                    @if($log->changer)<div class="program-timeline-meta">Oleh {{ $log->changer->name }}</div>@endif
                                    @if($log->catatan)<div class="program-timeline-note">{{ $log->catatan }}</div>@endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

        @if($isSuperAdmin || ! $editable || ($program->exists && ! in_array($program->status, ['draft', 'revisi'], true)))
            <a class="program-blue" href="{{ route($routePrefix.'.programs.index') }}">Kembali ke Overview</a>
        @endif
    </main>

    @if($program->exists && ! $isSuperAdmin)
        @foreach($documents as $document)
            <form id="delete-document-{{ $document->id }}" method="POST" action="{{ route('admin.program-documents.destroy', $document) }}" class="program-hidden">@csrf @method('DELETE')</form>
        @endforeach
    @endif

    @if(! $isSuperAdmin)
    <script>
        document.querySelectorAll('input[name="tipe_kerjasama"]').forEach((input) => input.addEventListener('change', () => {
            document.getElementById('jenis-kerjasama').value = input.value === 'NON PKS' ? 'non_pks' : 'pks';
        }));
        document.querySelectorAll('input[name="jenis_pengajuan"]').forEach((input) => input.addEventListener('change', () => {
            const field = document.getElementById('mitra-field');
            const external = input.value === 'external_stakeholder' && input.checked;
            field.classList.toggle('program-hidden', !external);
            field.querySelectorAll('input').forEach((control) => {
                control.required = external;
                control.disabled = !external;
            });
        }));
        document.querySelectorAll('[data-assistance-toggle]').forEach((input) => {
            const updateAssistanceFields = () => {
                const fields = document.querySelector(`[data-assistance-fields="${input.dataset.assistanceToggle}"]`);
                if (!fields) return;
                fields.classList.toggle('program-hidden', !input.checked);
                fields.querySelectorAll('input,textarea,select').forEach((control) => {
                    control.disabled = !input.checked;
                });
            };
            input.addEventListener('change', updateAssistanceFields);
            updateAssistanceFields();
        });
        const verificationToggles = Array.from(document.querySelectorAll('[data-verification-toggle]'));
        const toggleGroup = (element, active, requiredFields = []) => {
            if (!element) return;
            element.classList.toggle('program-hidden', !active);
            element.querySelectorAll('input,textarea,select').forEach((control) => {
                control.disabled = !active;
                control.required = active && requiredFields.includes(control.name);
            });
        };
        const updateVerificationFields = () => {
            const selectedTypes = verificationToggles.filter((input) => input.checked).map((input) => input.value);
            const hasMoney = selectedTypes.includes('uang');
            const hasGoods = selectedTypes.includes('barang');
            const hasService = selectedTypes.includes('jasa');
            const moneyFields = document.querySelector('[data-verification-fields="uang"]');
            const goodsFields = document.querySelector('[data-verification-fields="barang"]');
            const serviceFields = document.querySelector('[data-verification-fields="jasa"]');
            toggleGroup(moneyFields, hasMoney, ['sumber_pencairan', 'nama_pemilik_rekening', 'nomor_rekening', 'nama_bank']);
            toggleGroup(goodsFields, hasGoods, ['jalur_pengadaan_barang']);
            toggleGroup(serviceFields, hasService, ['kategori_jasa']);

            const source = document.querySelector('input[name="sumber_pencairan"]:checked')?.value;
            const cashOther = document.querySelector('[data-cash-other]');
            toggleGroup(cashOther, hasMoney && source === 'lainnya', ['sumber_pencairan_lainnya']);

            const goodsRoute = document.querySelector('input[name="jalur_pengadaan_barang"]:checked')?.value;
            const goodsSubRoute = document.querySelector('input[name="sub_jalur_pengadaan"]:checked')?.value;
            const procurement = document.querySelector('[data-procurement-fields]');
            const usedGoods = document.querySelector('[data-used-goods-fields]');
            const goodsVendors = document.querySelector('[data-goods-vendors]');
            toggleGroup(procurement, hasGoods && goodsRoute === 'proses_pengadaan', ['sub_jalur_pengadaan']);
            toggleGroup(usedGoods, hasGoods && goodsRoute === 'barang_bekas', ['sumber_barang', 'deskripsi_barang']);
            const goodsVendorActive = hasGoods && goodsRoute === 'proses_pengadaan' && goodsSubRoute === 'non_logistik';
            toggleGroup(goodsVendors, goodsVendorActive);
            goodsVendors?.querySelectorAll('.program-item-row input').forEach((control) => {
                control.required = goodsVendorActive;
            });

            const serviceType = document.getElementById('kategori-jasa')?.value;
            const serviceDescription = document.querySelector('[data-service-description]');
            const serviceVendors = document.querySelector('[data-service-vendors]');
            const serviceVendorsActive = hasService && serviceType === 'non_logistik';
            toggleGroup(serviceDescription, hasService && Boolean(serviceType), ['keterangan_jasa_verifikasi']);
            toggleGroup(serviceVendors, serviceVendorsActive);
            serviceVendors?.querySelectorAll('.program-item-row input').forEach((control) => {
                control.required = serviceVendorsActive;
            });
        };
        verificationToggles.forEach((input) => input.addEventListener('change', updateVerificationFields));
        document.querySelectorAll('#survey-form input[name="sumber_pencairan"], #survey-form input[name="jalur_pengadaan_barang"], #survey-form select[name="kategori_jasa"]')
            .forEach((input) => input.addEventListener('change', updateVerificationFields));
        document.querySelectorAll('#survey-form input[name="sub_jalur_pengadaan"]')
            .forEach((input) => input.addEventListener('change', updateVerificationFields));
        updateVerificationFields();

        const vendorList = document.querySelector('[data-vendor-list]');
        let nextVendorIndex = 0;
        vendorList?.querySelectorAll('input[name]').forEach((input) => {
            const match = input.name.match(/vendors\[(\d+)\]/);
            if (match) nextVendorIndex = Math.max(nextVendorIndex, Number(match[1]) + 1);
        });
        const appendVendorRow = (list, index, type) => {
            if (!list) return;
            const isService = type === 'service';
            const row = document.createElement('div');
            row.className = 'program-item-row verification-vendor-row';
            row.innerHTML = `
                <div class="program-field">
                    <label for="${type}-vendor-${index}-nama">Nama Vendor <span aria-hidden="true">*</span></label>
                    <input id="${type}-vendor-${index}-nama" name="${isService ? 'service_vendors' : 'vendors'}[${index}][nama_vendor]" placeholder="Nama Vendor">
                </div>
                <div class="program-field">
                    <label for="${type}-vendor-${index}-rincian">${isService ? 'Rincian Jasa' : 'Rincian Barang'} <span aria-hidden="true">*</span></label>
                    <input id="${type}-vendor-${index}-rincian" name="${isService ? 'service_vendors' : 'vendors'}[${index}][${isService ? 'rincian_jasa' : 'rincian_barang'}]" placeholder="${isService ? 'Rincian Jasa' : 'Rincian Barang'}">
                </div>
                <div class="program-field">
                    <label for="${type}-vendor-${index}-harga">Harga (Rp) <span aria-hidden="true">*</span></label>
                    <input id="${type}-vendor-${index}-harga" name="${isService ? 'service_vendors' : 'vendors'}[${index}][harga]" inputmode="numeric" placeholder="Masukkan harga">
                </div>
                <button class="program-remove-item" type="button" data-remove-item>Hapus</button>
            `;
            list.appendChild(row);
            updateVerificationFields();
        };
        document.querySelector('[data-add-vendor]')?.addEventListener('click', () => appendVendorRow(vendorList, nextVendorIndex++, 'goods'));
        const serviceVendorList = document.querySelector('[data-service-vendor-list]');
        let nextServiceVendorIndex = 0;
        serviceVendorList?.querySelectorAll('input[name]').forEach((input) => {
            const match = input.name.match(/service_vendors\[(\d+)\]/);
            if (match) nextServiceVendorIndex = Math.max(nextServiceVendorIndex, Number(match[1]) + 1);
        });
        document.querySelector('[data-add-service-vendor]')?.addEventListener('click', () => appendVendorRow(serviceVendorList, nextServiceVendorIndex++, 'service'));
        [vendorList, serviceVendorList].forEach((list) => list?.addEventListener('click', (event) => {
            if (event.target.closest('[data-remove-item]')) {
                event.target.closest('.program-item-row').remove();
                updateVerificationFields();
            }
        }));
        [vendorList, serviceVendorList].forEach((list) => list?.addEventListener('input', (event) => {
            if (event.target.matches('input[name$="[harga]"]')) {
                const digits = event.target.value.replace(/\D/g, '');
                event.target.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }
        }));
        const operationalNote = document.querySelector('[data-operational-note]');
        document.querySelectorAll('input[name="status_operasional"]').forEach((input) => input.addEventListener('change', () => {
            const showNote = input.checked && input.value === 'tertunda';
            if (!operationalNote) return;
            operationalNote.classList.toggle('program-hidden', !showNote);
            operationalNote.querySelector('textarea').disabled = !showNote;
        }));
        const operationalStatus = document.querySelector('input[name="status_operasional"]:checked');
        if (operationalStatus && operationalNote) {
            operationalNote.querySelector('textarea').disabled = operationalStatus.value !== 'tertunda';
        } else if (operationalNote) {
            operationalNote.querySelector('textarea').disabled = true;
        }
        document.querySelectorAll('input[name="kasbon_status"]').forEach((input) => input.addEventListener('change', () => {
            document.querySelectorAll('[data-kasbon-field]').forEach((field) => {
                const active = field.dataset.kasbonField === (input.value === 'disetujui' ? 'realisasi_anggaran' : 'catatan_penolakan');
                field.classList.toggle('program-hidden', !active);
                const control = field.querySelector('input,textarea');
                control.required = active;
            });
        }));
        const phase2Documentation = document.getElementById('phase2-documentation');
        const phase2DocumentationNames = document.querySelector('[data-phase2-file-names]');
        const phase2DocumentationPreview = document.getElementById('phase2-documentation-preview');
        const renderPhase2Documentation = () => {
            if (!phase2Documentation || !phase2DocumentationNames || !phase2DocumentationPreview) return;
            const files = Array.from(phase2Documentation.files);
            phase2DocumentationNames.textContent = files.length
                ? `${files.length} file dipilih`
                : 'Belum ada file dipilih';
            phase2DocumentationPreview.replaceChildren();
            files.forEach((file, index) => {
                const row = document.createElement('div');
                row.className = 'flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3 text-sm';
                if (file.type.startsWith('image/')) {
                    const preview = document.createElement('img');
                    preview.src = URL.createObjectURL(file);
                    preview.alt = '';
                    preview.className = 'h-12 w-12 shrink-0 rounded object-cover';
                    row.appendChild(preview);
                }
                const fileName = document.createElement('span');
                fileName.className = 'min-w-0 truncate';
                fileName.textContent = file.name;
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'program-delete shrink-0';
                remove.textContent = 'Hapus';
                remove.addEventListener('click', () => {
                    const transfer = new DataTransfer();
                    Array.from(phase2Documentation.files).forEach((selectedFile, selectedIndex) => {
                        if (selectedIndex !== index) transfer.items.add(selectedFile);
                    });
                    phase2Documentation.files = transfer.files;
                    renderPhase2Documentation();
                });
                row.append(fileName, remove);
                phase2DocumentationPreview.appendChild(row);
            });
        };
        phase2Documentation?.addEventListener('change', renderPhase2Documentation);
        const assistanceCounters = {};
        document.querySelectorAll('[data-item-list]').forEach((list) => {
            const type = list.dataset.itemList;
            let nextIndex = 0;
            list.querySelectorAll('[name]').forEach((control) => {
                const match = control.name.match(new RegExp(`assistance_items\\[${type}\\]\\[(\\d+)\\]`));
                if (match) nextIndex = Math.max(nextIndex, Number(match[1]) + 1);
            });
            assistanceCounters[type] = nextIndex;
        });
        document.querySelectorAll('[data-add-item]').forEach((button) => button.addEventListener('click', () => {
            const type = button.dataset.addItem;
            const list = document.querySelector(`[data-item-list="${type}"]`);
            if (!list) return;
            const index = assistanceCounters[type]++;
            const isGoods = type === 'barang';
            const row = document.createElement('div');
            row.className = 'program-item-row';
            row.innerHTML = `
                <input name="assistance_items[${type}][${index}][nama_item]" required placeholder="${isGoods ? 'Nama Barang' : 'Jenis / Rincian Layanan Jasa'}" aria-label="${isGoods ? 'Nama Barang' : 'Jenis Layanan Jasa'}">
                ${isGoods
                    ? `<input name="assistance_items[barang][${index}][jumlah]" placeholder="Jumlah / Kuantitas"><input name="assistance_items[barang][${index}][keterangan]" placeholder="Keterangan (opsional)">`
                    : `<input type="hidden" name="assistance_items[jasa][${index}][jumlah]" value=""><input name="assistance_items[jasa][${index}][keterangan]" placeholder="Keterangan (opsional)">`}
                <button class="program-remove-item" type="button" data-remove-item>Hapus</button>
            `;
            list.appendChild(row);
        }));
        document.querySelectorAll('[data-item-list]').forEach((list) => list.addEventListener('click', (event) => {
            if (event.target.closest('[data-remove-item]')) {
                event.target.closest('.program-item-row').remove();
            }
        }));
        const cashAmount = document.getElementById('nominal-uang-tunai');
        cashAmount?.addEventListener('input', () => {
            const digits = cashAmount.value.replace(/\D/g, '');
            cashAmount.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        });
        ['sisa-anggaran', 'nilai-program-realisasi'].forEach((id) => {
            const amount = document.getElementById(id);
            amount?.addEventListener('input', () => {
                const digits = amount.value.replace(/\D/g, '');
                amount.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            });
        });
        document.querySelectorAll('button[name="action"]').forEach((button) => button.addEventListener('click', () => {
            const proposalInput = document.querySelector('[data-required-for-submit]');
            if (proposalInput) proposalInput.required = button.value === 'submit';
        }));
        document.querySelectorAll('[data-add-document]').forEach((button) => button.addEventListener('click', () => {
            const rows = document.getElementById(button.dataset.addDocument);
            const field = button.dataset.field;
            const key = `tambahan_${Date.now()}_${Math.floor(Math.random()*1000)}`;
            const fileId = `file-${key}`;
            const row = document.createElement('div');
            row.className = 'program-doc-row';
            row.innerHTML = `<strong>Berkas Tambahan</strong><input name="${field}[${key}][nama]" placeholder="Masukkan nama dokumen"><div class="program-file-picker"><input id="${fileId}" type="file" name="${field}[${key}][file]"><label for="${fileId}">Pilih File</label><span class="program-filename" data-file-name>Belum ada file dipilih</span></div><span></span>`;
            rows.appendChild(row);
        }));
        const activityPhotoRows = document.getElementById('activity-photo-rows');
        document.querySelector('[data-add-photo]')?.addEventListener('click', () => {
            if (!activityPhotoRows) return;
            const key = `activity-photo-${Date.now()}-${Math.floor(Math.random() * 1000)}`;
            const row = document.createElement('div');
            row.className = 'program-doc-row';
            row.innerHTML = `<strong>Unggah foto tambahan</strong><span></span><div class="program-file-picker"><input id="${key}" type="file" name="activity_photos[]" accept="image/*" multiple><label for="${key}">Pilih Foto</label><span class="program-filename" data-file-name>Belum ada file dipilih</span></div><button class="program-remove-item" type="button" data-remove-photo-row>Hapus</button>`;
            activityPhotoRows.appendChild(row);
        });
        const finalReportForm = document.getElementById('final-report-form');
        const renderPhotoPreviews = () => {
            const preview = document.getElementById('activity-photo-preview');
            if (!preview || !finalReportForm) return;
            preview.innerHTML = '';
            finalReportForm.querySelectorAll('input[name="activity_photos[]"]').forEach((input) => {
                Array.from(input.files || []).forEach((file, index) => {
                    const item = document.createElement('div');
                    item.className = 'relative rounded border p-2 text-xs';
                    item.dataset.photoIndex = String(index);
                    item.dataset.photoInputId = input.id;
                    if (file.type.startsWith('image/')) {
                        const image = document.createElement('img');
                        image.src = URL.createObjectURL(file);
                        image.alt = file.name;
                        image.className = 'h-24 w-32 rounded object-cover';
                        item.appendChild(image);
                    }
                    const name = document.createElement('span');
                    name.className = 'block max-w-32 truncate';
                    name.textContent = file.name;
                    item.appendChild(name);
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'mt-1 text-red-700 underline';
                    remove.dataset.removeSelectedPhoto = 'true';
                    remove.textContent = 'Hapus';
                    item.appendChild(remove);
                    preview.appendChild(item);
                });
            });
        };
        finalReportForm?.addEventListener('change', (event) => {
            if (event.target.matches('input[name="activity_photos[]"]')) renderPhotoPreviews();
        });
        finalReportForm?.addEventListener('click', (event) => {
            const removePhoto = event.target.closest('[data-remove-selected-photo]');
            if (removePhoto) {
                const item = removePhoto.closest('[data-photo-index]');
                const input = document.getElementById(item.dataset.photoInputId);
                if (!input || typeof DataTransfer === 'undefined') return;
                const files = Array.from(input.files || []);
                const dataTransfer = new DataTransfer();
                files.forEach((file, index) => {
                    if (index !== Number(item.dataset.photoIndex)) dataTransfer.items.add(file);
                });
                input.files = dataTransfer.files;
                const filename = input.closest('.program-file-picker')?.querySelector('[data-file-name]');
                if (filename) filename.textContent = input.files[0]?.name || 'Belum ada file dipilih';
                renderPhotoPreviews();
            }
            if (event.target.closest('[data-remove-photo-row]')) event.target.closest('.program-doc-row').remove();
        });
    </script>
    @endif
    @if(! $isSuperAdmin)
        @include('admin.partials.file-validation', ['formId' => 'program-form'])
        @include('admin.partials.file-validation', ['formId' => 'survey-form'])
        @include('admin.partials.file-validation', ['formId' => 'final-report-form'])
    @endif
</x-layouts.admin>
