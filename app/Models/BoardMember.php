<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BoardMember extends Model
{
    public const DIVISIONS = [
        'inti' => 'Pengurus Inti',
        'okk' => 'Organisasi, Kaderisasi & Keanggotaan',
        'umkm' => 'UMKM & Ekonomi Kreatif',
        'humas' => 'Humas & Media',
        'investasi' => 'Investasi & Keuangan',
        'lainnya' => 'Bidang Lainnya',
    ];

    protected $fillable = [
        'name', 'position', 'division', 'photo', 'company', 'bio', 'instagram', 'linkedin', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
