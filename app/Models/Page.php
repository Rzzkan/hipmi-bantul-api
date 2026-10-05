<?php

namespace App\Models;

use App\Models\Concerns\HasPublishing;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasPublishing;

    protected $fillable = [
        'title', 'slug', 'hero', 'layout', 'meta_title', 'meta_description', 'meta_image', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'hero' => 'array',
            'layout' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
