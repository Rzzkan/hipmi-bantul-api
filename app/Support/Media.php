<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Media
{
    /** Disk used for all CMS images ("public" or "r2"), see config/filesystems.php → media_disk. */
    public static function diskName(): string
    {
        return config('filesystems.media_disk', 'public');
    }

    /** Convert a stored path into an absolute URL (base URL from admin → Pengaturan → Media). */
    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        // Legacy full URL to our own storage → path, so it follows the current base URL.
        $path = MediaPaths::toPath($path);

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path; // external image
        }

        return Storage::disk(static::diskName())->url($path);
    }
}
