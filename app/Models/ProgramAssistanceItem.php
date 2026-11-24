<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramAssistanceItem extends Model
{
    protected $fillable = [
        'program_id',
        'tipe',
        'nama_item',
        'jumlah',
        'keterangan',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
