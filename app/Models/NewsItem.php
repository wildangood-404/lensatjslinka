<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class NewsItem extends Model
{
    protected $fillable = [
        'title',
        'caption',
        'content',
        'source',
        'published_date',
        'image_path',
        'status',
        'created_by',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getImageUrlAttribute(): string
    {
        if (blank($this->image_path)) {
            return asset('images/news/tjsl-placeholder.svg');
        }

        if (str_starts_with($this->image_path, 'images/') || str_starts_with($this->image_path, '/')) {
            return asset(ltrim($this->image_path, '/'));
        }

        return '/storage/'.ltrim($this->image_path, '/');
    }
}
