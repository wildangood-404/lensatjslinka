<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PumkMitra extends Model
{
    protected $table = 'pumk_mitra';

    protected $fillable = [
        'nama_mitra',
        'jenis_usaha',
        'sektor_usaha_id',
        'sektor_sumber',
        'wilayah_id',
        'wilayah_sumber',
        'kabupaten_kota',
        'provinsi',
        'alamat',
        'nama_pemilik',
        'pasfoto',
        'nik',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'email',
        'alamat_ktp_provinsi',
        'alamat_ktp_kabupaten_kota',
        'alamat_ktp_kecamatan',
        'alamat_ktp_desa',
        'alamat_ktp_rt',
        'alamat_ktp_rw',
        'alamat_ktp_lengkap',
        'alamat_ktp_share_lok',
        'alamat_domisili_provinsi',
        'alamat_domisili_kabupaten_kota',
        'alamat_domisili_kecamatan',
        'alamat_domisili_desa',
        'alamat_domisili_rt',
        'alamat_domisili_rw',
        'alamat_domisili_lengkap',
        'alamat_domisili_share_lok',
        'alamat_domisili_sama_ktp',
        'alamat_usaha_provinsi',
        'alamat_usaha_kabupaten_kota',
        'alamat_usaha_kecamatan',
        'alamat_usaha_desa',
        'alamat_usaha_rt',
        'alamat_usaha_rw',
        'alamat_usaha_lengkap',
        'alamat_usaha_share_lok',
        'alamat_usaha_sama_ktp',
        'no_ktp_encrypted',
        'no_telepon_encrypted',
        'no_rekening_encrypted',
        'source_key',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'no_ktp_encrypted' => 'encrypted',
            'nik' => 'encrypted',
            'no_telepon_encrypted' => 'encrypted',
            'no_rekening_encrypted' => 'encrypted',
            'tanggal_lahir' => 'date',
            'alamat_domisili_sama_ktp' => 'boolean',
            'alamat_usaha_sama_ktp' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PumkMitra $mitra): void {
            $ktp = $mitra->no_ktp_encrypted;
            $mitra->no_ktp_hash = filled($ktp)
                ? hash_hmac('sha256', trim((string) $ktp), (string) config('app.key'))
                : null;
        });
    }

    public function sektorUsaha(): BelongsTo
    {
        return $this->belongsTo(PumkSektorUsaha::class, 'sektor_usaha_id');
    }

    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(PumkWilayah::class, 'wilayah_id');
    }

    public function getWilayahLabelAttribute(): ?string
    {
        $manualLocation = collect([$this->kabupaten_kota, $this->provinsi])
            ->filter(fn (?string $value): bool => filled($value))
            ->implode(', ');

        return $manualLocation !== ''
            ? $manualLocation
            : ($this->wilayah?->nama ?? $this->wilayah_sumber);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pinjaman(): HasMany
    {
        return $this->hasMany(PumkPinjaman::class, 'mitra_id');
    }

    public function pinjamanAktif(): HasMany
    {
        return $this->pinjaman()->where('status', PumkPinjaman::STATUS_AKTIF)->where('is_active', true);
    }

    public function classificationHistory(): HasMany
    {
        return $this->hasMany(PumkClassificationHistory::class, 'mitra_id');
    }

    public function dokumenPendukung(): HasMany
    {
        return $this->hasMany(PumkMitraDokumen::class, 'mitra_id');
    }
}
