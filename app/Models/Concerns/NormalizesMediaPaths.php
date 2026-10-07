<?php

namespace App\Models\Concerns;

use App\Support\MediaPaths;

/**
 * Before saving, turns image URLs into paths (see MediaPaths). Declare the columns with
 * `protected array $mediaAttributes = [...]` — image columns, rich-text HTML or JSON.
 */
trait NormalizesMediaPaths
{
    public static function bootNormalizesMediaPaths(): void
    {
        static::saving(function (self $model): void {
            foreach ($model->getMediaAttributes() as $attribute) {
                if ($model->isDirty($attribute)) {
                    $model->setAttribute($attribute, MediaPaths::normalize($model->getAttribute($attribute), $attribute));
                }
            }
        });
    }

    /** @return list<string> */
    public function getMediaAttributes(): array
    {
        return $this->mediaAttributes;
    }
}
