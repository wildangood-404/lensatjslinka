<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PumkBriAnnualFundSummary extends Model
{
    protected $table = 'pumk_bri_annual_fund_summaries';

    protected $fillable = [
        'tahun',
        'total_dana_ditransfer',
        'angsuran_pokok',
        'jasmin',
        'penyaluran',
        'os',
        'sisa_dana_akhir',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'total_dana_ditransfer' => 'decimal:0',
            'angsuran_pokok' => 'decimal:0',
            'jasmin' => 'decimal:0',
            'penyaluran' => 'decimal:0',
            'os' => 'decimal:0',
            'sisa_dana_akhir' => 'decimal:0',
        ];
    }
}
