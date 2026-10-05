<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pindahkan semua gambar dari disk server ("public") ke Cloudflare R2.
 *
 *   php artisan media:migrate-to-r2 --dry-run   # cek dulu
 *   php artisan media:migrate-to-r2             # salin + perbarui URL di konten
 *
 * Path file tetap sama, jadi kolom gambar (cover_image, photo, logo, …) tidak
 * perlu diubah. Yang diubah hanya URL absolut di dalam konten rich text.
 */
class MigrateMediaToR2 extends Command
{
    protected $signature = 'media:migrate-to-r2
        {--from=public : Disk asal}
        {--to=r2 : Disk tujuan}
        {--dry-run : Tampilkan rencana tanpa mengubah apa pun}';

    protected $description = 'Salin semua gambar CMS ke Cloudflare R2 dan perbarui URL gambar di konten';

    /** Tabel & kolom yang bisa berisi URL gambar absolut (rich text / JSON blocks). */
    protected array $contentColumns = [
        'posts' => ['content'],
        'events' => ['description'],
        'programs' => ['description'],
        'pages' => ['hero', 'layout'],
        'settings' => ['value'],
    ];

    public function handle(): int
    {
        $from = Storage::disk($this->option('from'));
        $to = Storage::disk($this->option('to'));
        $dry = (bool) $this->option('dry-run');

        $files = collect($from->allFiles())->reject(fn ($f) => basename($f) === '.gitignore')->values();
        $this->info("{$files->count()} file ditemukan di disk '{$this->option('from')}'.");

        $copied = $skipped = 0;
        $bar = $this->output->createProgressBar($files->count());
        foreach ($files as $path) {
            if ($to->exists($path)) {
                $skipped++;
            } elseif (! $dry) {
                $stream = $from->readStream($path);
                $to->writeStream($path, $stream, ['ContentType' => $from->mimeType($path) ?: 'application/octet-stream']);
                is_resource($stream) && fclose($stream);
                $copied++;
            } else {
                $copied++;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info(($dry ? '[dry-run] akan disalin: ' : 'Disalin: ')."{$copied} · sudah ada di tujuan: {$skipped}");

        $oldBase = rtrim($from->url(''), '/');
        $newBase = rtrim($to->url(''), '/');
        $this->line("URL lama: {$oldBase}\nURL baru: {$newBase}");

        $updated = $this->rewriteUrls($oldBase, $newBase, $dry);
        $this->info(($dry ? '[dry-run] baris konten yang akan diperbarui: ' : 'Baris konten diperbarui: ').$updated);

        if (! $dry) {
            $this->newLine();
            $this->warn('Langkah terakhir: set MEDIA_DISK=r2 di .env lalu jalankan: php artisan config:clear');
        }

        return self::SUCCESS;
    }

    protected function rewriteUrls(string $old, string $new, bool $dry): int
    {
        if ($old === $new) {
            return 0;
        }

        // JSON columns store "/" as "\/" — replace both spellings.
        $pairs = [$old => $new, str_replace('/', '\/', $old) => str_replace('/', '\/', $new)];
        $count = 0;

        foreach ($this->contentColumns as $table => $columns) {
            foreach (DB::table($table)->get(array_merge(['id'], $columns)) as $row) {
                $changes = [];
                foreach ($columns as $col) {
                    if (is_string($row->{$col}) && str_contains(str_replace('\/', '/', $row->{$col}), $old)) {
                        $changes[$col] = strtr($row->{$col}, $pairs);
                    }
                }
                if ($changes) {
                    $count++;
                    $dry || DB::table($table)->where('id', $row->id)->update($changes);
                }
            }
        }

        if ($count && ! $dry) {
            foreach (['site', 'header', 'footer'] as $key) {
                Cache::forget("setting.{$key}");
            }
        }

        return $count;
    }
}
