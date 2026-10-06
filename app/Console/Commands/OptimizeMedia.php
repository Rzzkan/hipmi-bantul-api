<?php

namespace App\Console\Commands;

use App\Support\ImageOptimizer;
use App\Support\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Compress images that were uploaded before automatic compression existed.
 *
 *   php artisan media:optimize --dry-run
 *   php artisan media:optimize
 *
 * Files keep their path & format (so nothing in the database changes); a file
 * is only replaced when the compressed version is actually smaller.
 */
class OptimizeMedia extends Command
{
    protected $signature = 'media:optimize {--disk= : Disk (default: MEDIA_DISK)} {--dry-run : Hitung penghematan tanpa menyimpan}';

    protected $description = 'Kompres ulang gambar lama sesuai ukuran tampil di website (path tetap sama)';

    public function handle(): int
    {
        $diskName = $this->option('disk') ?: Media::diskName();
        $disk = Storage::disk($diskName);
        $dry = (bool) $this->option('dry-run');
        $before = $after = $changed = 0;

        foreach (ImageOptimizer::DIRECTORY_PRESETS as $dir => $preset) {
            foreach ($disk->allFiles($dir) as $path) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }

                try {
                    $bytes = $disk->get($path);
                    $result = ImageOptimizer::optimize($bytes, $preset, $ext === 'jpeg' ? 'jpg' : $ext);
                } catch (Throwable $e) {
                    $this->warn("Lewati {$path}: {$e->getMessage()}");

                    continue;
                }

                $before += strlen($bytes);
                if (strlen($result['body']) < strlen($bytes) * 0.95) {
                    $after += strlen($result['body']);
                    $changed++;
                    $dry || $disk->put($path, $result['body'], ['ContentType' => $result['mime']]);
                    $this->line(sprintf('  %s  %s → %s', $path, $this->kb(strlen($bytes)), $this->kb(strlen($result['body']))));
                } else {
                    $after += strlen($bytes);
                }
            }
        }

        $saved = $before - $after;
        $this->info(sprintf(
            '%s%d file dikompres · total %s → %s (hemat %s)',
            $dry ? '[dry-run] ' : '', $changed, $this->kb($before), $this->kb($after), $this->kb($saved)
        ));

        return self::SUCCESS;
    }

    private function kb(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : round($bytes / 1024).' KB';
    }
}
