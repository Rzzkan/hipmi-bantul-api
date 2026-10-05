<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kompartemen di bawah sebuah bidang (satu bidang bisa punya banyak kompartemen). */
class Compartment extends Model
{
    protected $fillable = ['division_id', 'name', 'sort_order'];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(BoardMember::class);
    }
}
