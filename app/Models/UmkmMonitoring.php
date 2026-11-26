<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UmkmMonitoring extends Model
{
    protected $fillable = [
        'mitra_id',
        'periode_tahun',
        'jenis_kunjungan',
        'tindak_lanjut',
        'keterangan',
        'dokumentasi',
    ];

    protected function casts(): array
    {
        return [
            'periode_tahun' => 'integer',
            'dokumentasi' => 'array',
        ];
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(PumkMitra::class, 'mitra_id');
    }
}
