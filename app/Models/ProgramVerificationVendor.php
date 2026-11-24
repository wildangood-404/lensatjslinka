<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramVerificationVendor extends Model
{
    protected $fillable = [
        'nama_vendor',
        'rincian_barang',
        'harga',
    ];

    protected function casts(): array
    {
        return ['harga' => 'decimal:2'];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
