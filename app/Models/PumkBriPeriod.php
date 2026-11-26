<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PumkBriPeriod extends Model
{
    protected $table = 'pumk_bri_periods';

    protected $fillable = [
        'periode_tahun',
        'anggaran_rka',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'periode_tahun' => 'integer',
            'anggaran_rka' => 'decimal:0',
        ];
    }

    public function realisasi(): HasMany
    {
        return $this->hasMany(PumkBriRealization::class, 'pumk_bri_period_id')->orderBy('waktu')->orderBy('id');
    }
}
