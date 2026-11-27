<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PumkBriSnapshotBulanan extends Model
{
    protected $table = 'pumk_bri_snapshot_bulanan';

    protected $fillable = [
        'mitra_id',
        'fasilitas_id',
        'bulan',
        'tahun',
        'saldo_piutang',
        'kolektibilitas_kode',
        'kolektibilitas_label',
        'source_sheet',
        'source_row',
        'no_urut_sumber',
        'nama_mitra_sumber',
        'alamat_sumber',
        'wilayah_sumber',
        'sektor_usaha_sumber',
        'pinjaman_sumber',
        'tenor_sumber',
        'tanggal_pencairan_sumber',
        'tanggal_jatuh_tempo_sumber',
        'profil_sumber_terverifikasi',
        'source_payload',
        'kode_unit',
        'bri_unit',
        'nama_mitra_binaan',
        'alamat',
        'provinsi',
        'kabupaten_kota',
        'sektor_ekonomi_bumn',
        'jangka_waktu',
        'tanggal_realisasi',
        'tanggal_jatuh_tempo',
        'jumlah_realisasi',
        'outstanding',
        'kolektibilitas_bumn',
        'tanggal_lunas',
        'jenis_usaha',
        'accrued_interest',
        'angsuran_bunga_diterima_giro',
    ];

    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'tahun' => 'integer',
            'saldo_piutang' => 'decimal:2',
            'source_row' => 'integer',
            'no_urut_sumber' => 'integer',
            'pinjaman_sumber' => 'decimal:2',
            'tanggal_pencairan_sumber' => 'date',
            'tanggal_jatuh_tempo_sumber' => 'date',
            'profil_sumber_terverifikasi' => 'boolean',
            'source_payload' => 'array',
            'tanggal_realisasi' => 'date',
            'tanggal_jatuh_tempo' => 'date',
            'tanggal_lunas' => 'date',
            'jumlah_realisasi' => 'decimal:2',
            'outstanding' => 'decimal:2',
            'accrued_interest' => 'decimal:2',
            'angsuran_bunga_diterima_giro' => 'decimal:2',
        ];
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(PumkBriMitra::class, 'mitra_id');
    }

    public function fasilitas(): BelongsTo
    {
        return $this->belongsTo(PumkBriFasilitas::class, 'fasilitas_id');
    }
}
