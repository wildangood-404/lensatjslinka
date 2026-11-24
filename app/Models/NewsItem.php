<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsItem extends Model
{
    protected $fillable = [
        'title',
        'source',
        'published_date',
        'image_path',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getImageUrlAttribute(): string
    {
        if (str_starts_with($this->image_path, 'images/') || str_starts_with($this->image_path, '/')) {
            return asset(ltrim($this->image_path, '/'));
        }

        return '/storage/'.ltrim($this->image_path, '/');
    }
}
