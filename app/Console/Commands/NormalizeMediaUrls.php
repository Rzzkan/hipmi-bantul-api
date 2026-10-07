<?php

namespace App\Console\Commands;

use App\Models\BoardMember;
use App\Models\Event;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Program;
use App\Models\Setting;
use App\Support\ImageOptimizer;
use App\Support\MediaPaths;
use App\Support\RemoteImage;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Converts image URLs already stored in the database into paths.
 *
 *   php artisan media:normalize-urls --dry-run     # lihat apa yang akan berubah
 *   php artisan media:normalize-urls               # ubah URL milik sendiri → path
 *   php artisan media:normalize-urls --download    # + unduh gambar dari situs lain ke R2
 */
class NormalizeMediaUrls extends Command
{
    protected $signature = 'media:normalize-urls
        {--dry-run : Tampilkan perubahan tanpa menyimpan}
        {--download : Unduh & kompres gambar dari situs lain ke penyimpanan media}';

    protected $description = 'Simpan semua gambar sebagai path (bukan URL lengkap) agar mengikuti Base URL R2 dari admin';

    /** @var list<class-string<Model>> */
    public const MODELS = [Post::class, Event::class, Program::class, Partner::class, BoardMember::class, Page::class, Setting::class];

    private const PRESETS = [
        'cover_image' => 'cover', 'logo' => 'logo', 'photo' => 'avatar', 'avatar' => 'avatar',
        'media' => 'block', 'image' => 'block', 'meta_image' => 'og',
    ];

    private int $changed = 0;

    /** @var list<string> */
    private array $external = [];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        foreach (self::MODELS as $class) {
            $class::query()->lazyById()->each(function (Model $record) use ($dry) {
                $dirty = [];

                foreach ($record->getMediaAttributes() as $attribute) {
                    $before = $record->getAttribute($attribute);
                    $after = $this->importExternal(MediaPaths::normalize($before, $attribute), $attribute, $record, $dry);

                    if ($after !== $before) {
                        $dirty[$attribute] = $after;
                    }
                }

                if ($dirty) {
                    $this->changed++;
                    $this->line(sprintf('  %s #%s: %s', class_basename($record), $record->getKey(), implode(', ', array_keys($dirty))));
                    if (! $dry) {
                        $record->forceFill($dirty)->saveQuietly();
                    }
                }
            });
        }

        $this->newLine();
        $this->info(($dry ? '[dry-run] ' : '')."{$this->changed} data ".($dry ? 'akan' : 'sudah').' diubah ke path.');

        if ($this->external && ! $this->option('download')) {
            $this->warn(count($this->external).' gambar masih memakai URL situs lain (tetap tampil, tapi tidak ikut Base URL R2):');
            foreach (array_unique($this->external) as $url) {
                $this->line("  - {$url}");
            }
            $this->line('Jalankan dengan --download untuk memindahkannya ke R2.');
        }

        // Settings are cached forever.
        Setting::query()->pluck('key')->each(fn ($key) => cache()->forget("setting.{$key}"));

        return self::SUCCESS;
    }

    /** Walk image keys; external URLs are reported or (with --download) imported. */
    private function importExternal(mixed $value, ?string $key, Model $record, bool $dry): mixed
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->importExternal($v, is_string($k) ? $k : $key, $record, $dry);
            }

            return $value;
        }

        if (! is_string($value) || ! isset(self::PRESETS[$key]) || ! MediaPaths::isExternal($value)) {
            return $value;
        }

        if (! $this->option('download') || $dry) {
            $this->external[] = $value;

            return $value;
        }

        try {
            $preset = $record instanceof Event && $key === 'cover_image' ? 'poster' : self::PRESETS[$key];
            $path = ImageOptimizer::store(RemoteImage::fetch($value), $preset, 'imported');
            $this->line("  ↓ {$value} → {$path}");

            return $path;
        } catch (Throwable $e) {
            $this->warn("  ! gagal unduh {$value}: {$e->getMessage()}");
            $this->external[] = $value;

            return $value;
        }
    }
}
