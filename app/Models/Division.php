<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bidang kepengurusan (Bidang 1 … Bidang 12). */
class Division extends Model
{
    protected $fillable = ['number', 'name', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'number' => 'integer'];
    }

    public function compartments(): HasMany
    {
        return $this->hasMany(Compartment::class)->orderBy('sort_order')->orderBy('id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(BoardMember::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('number')->orderBy('id');
    }

    /** "Bidang 1 — Organisasi, …" */
    public function getLabelAttribute(): string
    {
        return ($this->number ? "Bidang {$this->number} — " : '').$this->name;
    }
}
