<?php

namespace App\Models;

use App\Models\Concerns\HasPublishing;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasPublishing;

    public const CATEGORIES = [
        'berita' => 'Berita',
        'kegiatan' => 'Kegiatan',
        'opini' => 'Opini',
        'pengumuman' => 'Pengumuman',
    ];

    protected $fillable = [
        'title', 'slug', 'category', 'excerpt', 'cover_image', 'content', 'author_name',
        'meta_title', 'meta_description', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }
}
