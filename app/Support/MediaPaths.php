<?php

namespace App\Support;

use App\Models\Setting;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;
use Throwable;

/**
 * Images are always stored in the database as a PATH relative to the media disk
 * (e.g. "posts/01jabc….webp"), never as a full URL. The public URL is built only when
 * the API responds (Media::url), from the base URL set in admin → Pengaturan → Media.
 * So changing the R2 domain/bucket = change one setting; no database edits.
 *
 * - Image fields (cover_image, logo, photo, media, …): own URL → path.
 * - Rich-text HTML: <img data-id="path"> is stored without src; src is filled in at output.
 * - URLs from other sites (not our media) are left alone — `media:normalize-urls --download`
 *   can import them.
 */
class MediaPaths
{
    /** JSON/columns whose string value is an image. */
    public const IMAGE_KEYS = ['media', 'image', 'logo', 'photo', 'meta_image', 'cover_image', 'avatar'];

    public const SETTING_KEY = 'media';

    /* ------------------------------------------------------------------ */
    /* Base URL (configurable from admin) */
    /* ------------------------------------------------------------------ */

    /** Base URL R2 from admin settings (null = use R2_PUBLIC_URL from .env). */
    public static function configuredBaseUrl(): ?string
    {
        try {
            $url = Setting::get(self::SETTING_KEY, [])['public_url'] ?? null;
        } catch (Throwable) {
            return null; // database not ready yet (install / migrations)
        }

        return filled($url) ? rtrim($url, '/') : null;
    }

    /** Apply the admin base URL to the r2 disk. Called on boot and after saving the setting. */
    public static function applyConfiguredBaseUrl(): void
    {
        if ($url = static::configuredBaseUrl()) {
            config(['filesystems.disks.r2.url' => $url]);
        }

        app('filesystem')->forgetDisk('r2');
    }

    /**
     * Every base under which our own files have been (or may be) published, so their
     * full URLs can be turned back into paths. Includes old base URLs set in admin.
     *
     * @return list<string> e.g. ["https://media.hipmibantul.com/cms/", "https://api.hipmibantul.com/storage/"]
     */
    public static function knownPrefixes(): array
    {
        $r2Root = trim((string) config('filesystems.disks.r2.root'), '/');
        $setting = rescue(fn () => Setting::get(self::SETTING_KEY, []), [], false);

        $r2Bases = array_filter([
            config('filesystems.disks.r2.url'),
            env('R2_PUBLIC_URL'),
            $setting['public_url'] ?? null,
            ...($setting['previous_urls'] ?? []),
        ]);

        $prefixes = [];
        foreach ($r2Bases as $base) {
            $base = rtrim($base, '/');
            if ($r2Root !== '') {
                $prefixes[] = "{$base}/{$r2Root}/";
            }
            $prefixes[] = "{$base}/";
        }

        foreach (array_filter([config('filesystems.disks.public.url'), rtrim((string) config('app.url'), '/').'/storage']) as $base) {
            $prefixes[] = rtrim($base, '/').'/';
        }

        // longest first, so ".../cms/" wins over "..."
        $prefixes = array_values(array_unique($prefixes));
        usort($prefixes, fn ($a, $b) => strlen($b) <=> strlen($a));

        return $prefixes;
    }

    /* ------------------------------------------------------------------ */
    /* Normalize (before saving) */
    /* ------------------------------------------------------------------ */

    /** Our own media URL → path. Other values are returned unchanged. */
    public static function toPath(?string $value): ?string
    {
        if (blank($value)) {
            return $value;
        }

        $value = trim($value);

        if (! Str::startsWith($value, ['http://', 'https://', '//'])) {
            return ltrim($value, '/');
        }

        $comparable = static::withoutScheme($value);
        foreach (static::knownPrefixes() as $prefix) {
            if (Str::startsWith($comparable, static::withoutScheme($prefix))) {
                $path = substr($comparable, strlen(static::withoutScheme($prefix)));

                return rawurldecode(strtok($path, '?#') ?: $path);
            }
        }

        return $value; // external image — left as-is
    }

    public static function isExternal(?string $value): bool
    {
        return filled($value) && Str::startsWith($value, ['http://', 'https://', '//']);
    }

    /**
     * Normalize any stored value: image keys → path, HTML → <img data-id> without src,
     * arrays (Page hero/layout, settings) recursively.
     */
    public static function normalize(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = static::normalize($v, is_string($k) ? $k : $key);
            }

            return $value;
        }

        if (! is_string($value) || $value === '') {
            return $value;
        }

        if ($key !== null && in_array($key, self::IMAGE_KEYS, true)) {
            return static::toPath($value);
        }

        if (str_contains($value, '<img')) {
            return static::normalizeHtml($value);
        }

        return $value;
    }

    /** Store editor images as <img data-id="path"> (no absolute src). */
    public static function normalizeHtml(string $html): string
    {
        return static::rewriteImages($html, function (DOMElement $img): void {
            $id = $img->getAttribute('data-id');
            $src = $img->getAttribute('src');

            if ($id === '' && $src !== '') {
                $path = static::toPath($src);
                if ($path === $src) {
                    return; // external image: keep its src
                }
                $img->setAttribute('data-id', $path);
            } elseif ($id !== '') {
                $img->setAttribute('data-id', static::toPath($id));
            }

            $img->removeAttribute('src');
        });
    }

    /* ------------------------------------------------------------------ */
    /* Resolve (when responding) */
    /* ------------------------------------------------------------------ */

    /** Fill src of <img data-id> from the current base URL. */
    public static function resolveHtml(string $html): string
    {
        if (! str_contains($html, 'data-id')) {
            return $html;
        }

        return static::rewriteImages($html, function (DOMElement $img): void {
            $id = $img->getAttribute('data-id');
            if ($id !== '') {
                $img->setAttribute('src', (string) Media::url($id));
            }
        });
    }

    /* ------------------------------------------------------------------ */

    private static function withoutScheme(string $url): string
    {
        return preg_replace('~^(https?:)?//~i', '', $url);
    }

    /** Edit only <img> tags, leaving the rest of the HTML byte-for-byte unchanged. */
    private static function rewriteImages(string $html, callable $edit): string
    {
        return preg_replace_callback('~<img\b[^>]*>~i', function (array $m) use ($edit) {
            $dom = new DOMDocument;
            $ok = @$dom->loadHTML('<?xml encoding="UTF-8"><body>'.$m[0].'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
            $img = $ok ? $dom->getElementsByTagName('img')->item(0) : null;
            if (! $img instanceof DOMElement) {
                return $m[0];
            }

            $edit($img);

            return $dom->saveHTML($img);
        }, $html) ?? $html;
    }
}
