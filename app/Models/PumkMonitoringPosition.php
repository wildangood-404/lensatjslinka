<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PumkMonitoringPosition extends Model
{
    protected $table = 'pumk_monitoring_positions';

    protected $fillable = [
        'report_id', 'pinjaman_id', 'mitra_id', 'saldo_pokok', 'saldo_bunga',
        'sektor', 'wilayah', 'kolektibilitas', 'classification_limited', 'source_kind',
    ];

    protected function casts(): array
    {
        return [
            'saldo_pokok' => 'decimal:2',
            'saldo_bunga' => 'decimal:2',
            'classification_limited' => 'boolean',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(PumkMonitoringReport::class, 'report_id');
    }

    public function pinjaman(): BelongsTo
    {
        return $this->belongsTo(PumkPinjaman::class, 'pinjaman_id');
    }
}
