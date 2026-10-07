<?php

namespace App\Models;

use App\Models\Concerns\NormalizesMediaPaths;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    use NormalizesMediaPaths;

    public const TIERS = [
        'platinum' => 'Platinum Sponsor',
        'gold' => 'Gold Sponsor',
        'partner' => 'Partner',
        'media' => 'Media Partner',
    ];

    /** Image/HTML columns stored as paths (see MediaPaths). */
    protected array $mediaAttributes = ['logo'];

    protected $fillable = ['name', 'logo', 'url', 'tier', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
