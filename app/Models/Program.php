<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    public const COOPERATION_TYPES = [
        'pks',
        'non_pks',
    ];

    public const WORKFLOW_STATUSES = [
        'draft',
        'menunggu_review_proposal',
        'revisi',
        'proposal_approve',
        'menunggu_review_survei',
        'perbaikan',
        'survei_disetujui',
        'tertunda',
        'pelaksanaan',
        'kasbon_disetujui',
        'kasbon_ditolak',
        'menunggu_review_laporan_akhir',
        'revisi_laporan_akhir',
        'selesai',
        'ditolak',
    ];

    public const PHASE_ONE_DOCUMENTS = [
        'proposal_pengajuan_program' => 'Proposal Program',
        'kelengkapan_survei' => 'Kelengkapan Survei',
        'kajian_kelayakan' => 'Kajian Kelayakan Kerja Sama',
        'kajian_mitigasi_risiko' => 'Kajian Risiko',
        'perjanjian_kerja_sama' => 'Perjanjian Kerja Sama',
    ];

    public const PUBLIC_STATUSES = [
        'pending_fase1',
        'approved_fase1',
        'pending_fase2',
        'completed',
        'proposal_approve',
        'menunggu_review_survei',
        'survei_disetujui',
        'tertunda',
        'pelaksanaan',
        'kasbon_disetujui',
        'menunggu_review_laporan_akhir',
        'selesai',
    ];

    /**
     * Program yang sudah memperoleh persetujuan awal Super Admin dan layak
     * ditampilkan pada Overview Program TJSL.
     */
    public const OVERVIEW_STATUSES = [
        'approved_fase1',
        'pending_fase2',
        'completed',
        'proposal_approve',
        'menunggu_review_survei',
        'survei_disetujui',
        'tertunda',
        'pelaksanaan',
        'kasbon_disetujui',
        'menunggu_review_laporan_akhir',
        'selesai',
    ];

    protected $fillable = [
        'slug',
        'pillar_id',
        'jenis_kerjasama',
        'tipe_kerjasama',
        'jenis_pengajuan',
        'nama_mitra',
        'no_surat',
        'tanggal_surat',
        'kategori_program',
        'nama_program',
        'deskripsi_program',
        'tanggal_program',
        'pilar_verifikasi',
        'kesesuaian_rka',
        'no_tpb',
        'jenis_program_tpb',
        'sisa_anggaran',
        'kebutuhan_program',
        'tanggal_pelaksanaan',
        'keterangan_verifikasi',
        'jenis_bantuan_verifikasi',
        'sumber_pencairan',
        'sumber_pencairan_lainnya',
        'nama_pemilik_rekening',
        'nomor_rekening',
        'nama_bank',
        'jalur_pengadaan_barang',
        'sub_jalur_pengadaan',
        'sumber_barang',
        'deskripsi_barang',
        'kategori_jasa',
        'keterangan_jasa_verifikasi',
        'jalur_pengajuan',
        'status_operasional',
        'catatan_operasional',
        'nama_program_laporan',
        'nilai_program_realisasi',
        'no_dokumen_laporan',
        'nama_dokumen_laporan',
        'sasaran_program',
        'lokasi_program',
        'mitra_program',
        'rencana_anggaran',
        'jenis_bantuan',
        'nominal_uang_tunai',
        'detail_jasa',
        'pic_nama',
        'pic_telepon',
        'realisasi_anggaran',
        'tujuan_program',
        'status',
        'is_archived',
        'created_by',
        'submitted_at',
        'fase1_reviewed_by',
        'fase1_reviewed_at',
        'fase1_rejected_reason',
        'fase2_reviewed_by',
        'fase2_reviewed_at',
        'fase2_rejected_reason',
        'catatan_terakhir',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'submitted_at' => 'datetime',
            'fase1_reviewed_at' => 'datetime',
            'fase2_reviewed_at' => 'datetime',
            'tanggal_surat' => 'date',
            'tanggal_program' => 'date',
            'tanggal_pelaksanaan' => 'date',
            'rencana_anggaran' => 'decimal:2',
            'sisa_anggaran' => 'decimal:2',
            'nilai_program_realisasi' => 'decimal:2',
            'jenis_bantuan' => 'array',
            'jenis_bantuan_verifikasi' => 'array',
            'jalur_pengajuan' => 'array',
            'nominal_uang_tunai' => 'integer',
            'realisasi_anggaran' => 'decimal:2',
        ];
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(Pillar::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fase1Reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fase1_reviewed_by');
    }

    public function fase2Reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fase2_reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProgramDocument::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProgramLog::class)->latest();
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProgramPhoto::class)->orderBy('order');
    }

    public function tujuan(): HasMany
    {
        return $this->hasMany(ProgramTujuan::class)->orderBy('urutan');
    }

    public function assistanceItems(): HasMany
    {
        return $this->hasMany(ProgramAssistanceItem::class)->orderBy('id');
    }

    public function verificationVendors(): HasMany
    {
        return $this->hasMany(ProgramVerificationVendor::class)->orderBy('id');
    }

    public function getEffectivePilarAttribute(): ?string
    {
        return $this->pilar_verifikasi ?: ($this->kategori_program ?: $this->pillar?->name);
    }

    public function bantuanCsr(): HasMany
    {
        return $this->hasMany(BantuanCsr::class);
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'revisi', 'perbaikan', 'revisi_laporan_akhir'], true)
            && ! $this->is_archived;
    }

    public function canUploadBast(): bool
    {
        return $this->status === 'approved_fase1' && ! $this->is_archived;
    }

    /**
     * Dokumen awal yang wajib untuk jenis kerja sama Program Internal ini.
     *
     * Program lama yang belum memiliki nilai jenis_kerjasama tetap dianggap PKS
     * agar alur dan kelengkapan dokumennya tidak berubah.
     */
    public function phaseOneDocuments(): array
    {
        if ($this->jenis_kerjasama !== 'non_pks') {
            return self::PHASE_ONE_DOCUMENTS;
        }

        return array_intersect_key(
            self::PHASE_ONE_DOCUMENTS,
            array_flip([
                'proposal_pengajuan_program',
                'kelengkapan_survei',
            ]),
        );
    }

    public function cooperationLabel(): string
    {
        return in_array($this->tipe_kerjasama, ['NON PKS', 'NON-PKS'], true)
            || $this->jenis_kerjasama === 'non_pks'
            ? 'NON PKS'
            : 'PKS';
    }
}
