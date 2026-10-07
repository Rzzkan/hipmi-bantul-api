<?php

namespace App\Models;

use App\Models\Concerns\HasPublishing;
use App\Models\Concerns\NormalizesMediaPaths;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasPublishing, NormalizesMediaPaths;

    /** Image/HTML columns stored as paths (see MediaPaths). */
    protected array $mediaAttributes = ['hero', 'layout', 'meta_image'];

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
