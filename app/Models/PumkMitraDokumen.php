<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PumkMitraDokumen extends Model
{
    protected $table = 'pumk_mitra_dokumen';

    protected $fillable = [
        'mitra_id',
        'file_path',
        'nama_file_asli',
        'mime_type',
        'file_size',
        'uploaded_by',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(PumkMitra::class, 'mitra_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
