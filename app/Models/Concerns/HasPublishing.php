<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Draft / published workflow + auto slug, mirroring Payload's `_status` & slug field.
 */
trait HasPublishing
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public static function bootHasPublishing(): void
    {
        static::saving(function ($model) {
            if (blank($model->slug)) {
                $model->slug = static::uniqueSlug($model->title, $model->getKey());
            }

            if ($model->status === self::STATUS_PUBLISHED && blank($model->published_at)) {
                $model->published_at = now();
            }
        });
    }

    public static function uniqueSlug(string $title, $ignoreId = null): string
    {
        $base = Str::slug($title) ?: Str::random(8);
        $slug = $base;
        $i = 2;

        while (static::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public static function statusOptions(): array
    {
        return [self::STATUS_DRAFT => 'Draft', self::STATUS_PUBLISHED => 'Published'];
    }
}
