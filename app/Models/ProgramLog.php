<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramLog extends Model
{
    protected $fillable = ['program_id', 'from_status', 'to_status', 'changed_by', 'catatan'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
