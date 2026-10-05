<?php

namespace App\Models;

use App\Models\Concerns\HasPublishing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use HasPublishing;

    public const CATEGORIES = [
        'okk' => 'Kaderisasi (OKK)',
        'umkm' => 'Pendampingan UMKM',
        'sosial' => 'Sosial & Charity',
        'networking' => 'Networking & Sinergi',
        'pelatihan' => 'Pelatihan',
    ];

    protected $fillable = [
        'title', 'slug', 'category', 'summary', 'cover_image', 'description', 'benefits', 'price',
        'registration_open', 'is_featured', 'sort_order', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'benefits' => 'array',
            'registration_open' => 'boolean',
            'is_featured' => 'boolean',
            'price' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
