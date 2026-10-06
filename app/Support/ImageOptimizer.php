<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

/**
 * Compresses every CMS image before it is stored (R2 or server disk).
 *
 * Each preset is sized for how the image is actually displayed on the website
 * (with headroom for retina screens), then re-encoded — WebP by default.
 * Next.js `next/image` still serves smaller responsive variants on top of this,
 * so these are the "master" files: as small as possible without visible loss.
 */
class ImageOptimizer
{
    /**
     * width/height = max bounding box (px) · fit: scaleDown keeps aspect, cover crops to exact size.
     *
     * @var array<string, array{label: string, width: int, height: int, fit: string, format: string, quality: int}>
     */
    public const PRESETS = [
        // Full-width hero background (up to ~1440px wide × 2 for retina, capped)
        'hero' => ['label' => 'Hero (lebar penuh)', 'width' => 2400, 'height' => 1600, 'fit' => 'scaleDown', 'format' => 'webp', 'quality' => 78],
        // Image block inside a page (container ≤ 1280px)
        'block' => ['label' => 'Gambar di halaman', 'width' => 2000, 'height' => 2000, 'fit' => 'scaleDown', 'format' => 'webp', 'quality' => 80],
        // Covers for news & programs (cards + article header ≤ 800px wide)
        'cover' => ['label' => 'Sampul berita/program', 'width' => 1600, 'height' => 1600, 'fit' => 'scaleDown', 'format' => 'webp', 'quality' => 80],
        // Event posters are often portrait (4:5) — allow more height
        'poster' => ['label' => 'Poster agenda', 'width' => 1600, 'height' => 2000, 'fit' => 'scaleDown', 'format' => 'webp', 'quality' => 82],
        // Board member photos — shown as circles ≤ 144px; 600px is plenty for retina
        'avatar' => ['label' => 'Foto pengurus', 'width' => 600, 'height' => 600, 'fit' => 'cover', 'format' => 'webp', 'quality' => 82],
        // Logos (partners, site) — keep transparency, small but crisp
        'logo' => ['label' => 'Logo', 'width' => 600, 'height' => 600, 'fit' => 'scaleDown', 'format' => 'webp', 'quality' => 90],
        // Social share image (WhatsApp/Facebook/LinkedIn preview) — exact 1200×630, JPEG for max compatibility
        'og' => ['label' => 'Gambar share (OG)', 'width' => 1200, 'height' => 630, 'fit' => 'cover', 'format' => 'jpg', 'quality' => 85],
        // Images inserted in the rich-text editor (article column ≤ 768px)
        'editor' => ['label' => 'Gambar dalam artikel', 'width' => 1600, 'height' => 1600, 'fit' => 'scaleDown', 'format' => 'webp', 'quality' => 80],
    ];

    /** Formats we can decode & re-encode with GD. */
    public const ACCEPTED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** Max upload size before compression (KB). Phone photos are typically 3–8 MB. */
    public const MAX_UPLOAD_KB = 15360;

    public static function preset(string $name): array
    {
        return self::PRESETS[$name] ?? throw new InvalidArgumentException("Unknown image preset [{$name}]");
    }

    /**
     * Compress raw image bytes according to a preset.
     *
     * @return array{body: string, extension: string, mime: string, width: int|null, height: int|null}
     */
    public static function optimize(string $bytes, string $preset, ?string $format = null): array
    {
        $p = self::preset($preset);
        $p['format'] = $format ?? $p['format'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';

        // SVG (logos only) is vector — keep as-is. Animated GIFs would lose their animation.
        if ($mime === 'image/svg+xml') {
            return ['body' => $bytes, 'extension' => 'svg', 'mime' => $mime, 'width' => null, 'height' => null];
        }

        if (! in_array($mime, self::ACCEPTED_MIME, true)) {
            throw new InvalidArgumentException('Format gambar tidak didukung. Gunakan JPG, PNG, WebP, atau GIF.');
        }

        $manager = new ImageManager(new Driver); // auto-orients from EXIF (foto HP tidak miring)
        $image = $manager->read($bytes);

        if ($mime === 'image/gif' && $image->isAnimated()) {
            return ['body' => $bytes, 'extension' => 'gif', 'mime' => $mime, 'width' => $image->width(), 'height' => $image->height()];
        }

        if ($p['fit'] === 'cover') {
            $image->cover($p['width'], $p['height']);
        } else {
            $image->scaleDown($p['width'], $p['height']);
        }

        $encoded = match ($p['format']) {
            'jpg', 'jpeg' => $image->toJpeg(quality: $p['quality'], progressive: true),
            'png' => $image->toPng(),
            default => $image->toWebp(quality: $p['quality']),
        };

        return [
            'body' => (string) $encoded,
            'extension' => $p['format'],
            'mime' => $encoded->mimetype(),
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }

    /** Which preset fits files already stored in each upload directory. */
    public const DIRECTORY_PRESETS = [
        'hero' => 'hero', 'blocks' => 'block', 'posts' => 'cover', 'programs' => 'cover', 'events' => 'poster',
        'board' => 'avatar', 'partners' => 'logo', 'seo' => 'og', 'editor' => 'editor',
    ];

    /**
     * Compress and store an uploaded file (or raw bytes) on the media disk. Returns the stored path.
     */
    public static function store(UploadedFile|string $source, string $preset, string $directory): string
    {
        $bytes = $source instanceof UploadedFile ? (string) file_get_contents($source->getRealPath()) : $source;
        $result = self::optimize($bytes, $preset);

        $path = trim($directory, '/').'/'.Str::ulid()->toBase32().'.'.$result['extension'];
        $path = strtolower($path);

        Storage::disk(Media::diskName())->put($path, $result['body'], [
            'ContentType' => $result['mime'],
            'CacheControl' => 'public, max-age=31536000, immutable', // nama file unik → aman di-cache lama
        ]);

        return $path;
    }
}
