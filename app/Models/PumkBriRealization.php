<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PumkBriRealization extends Model
{
    protected $table = 'pumk_bri_realizations';

    protected $fillable = [
        'pumk_bri_period_id',
        'uraian',
        'waktu',
        'pic',
        'nominal',
        'dokumen_path',
        'dokumen_nama',
    ];

    protected function casts(): array
    {
        return [
            'waktu' => 'date',
            'nominal' => 'decimal:0',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PumkBriPeriod::class, 'pumk_bri_period_id');
    }
}
