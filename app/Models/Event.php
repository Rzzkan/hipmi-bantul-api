<?php

namespace App\Models;

use App\Models\Concerns\HasPublishing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasPublishing;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'cover_image', 'description', 'start_at', 'end_at', 'location',
        'location_url', 'price', 'quota', 'registration_open', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'published_at' => 'datetime',
            'registration_open' => 'boolean',
            'price' => 'integer',
            'quota' => 'integer',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->where('start_at', '>=', now()->startOfDay())->orWhere('end_at', '>=', now()));
    }

    public function seatsLeft(): ?int
    {
        if (! $this->quota) {
            return null;
        }

        $taken = $this->registrations()->where('status', '!=', Registration::STATUS_REJECTED)->count();

        return max(0, $this->quota - $taken);
    }

    public function acceptsRegistration(): bool
    {
        $finished = ($this->end_at ?? $this->start_at)?->isPast();

        return $this->registration_open && ! $finished && $this->seatsLeft() !== 0;
    }
}
