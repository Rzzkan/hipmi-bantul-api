<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sementara: halaman /daftar memakai Google Form OKK, bukan formulir bawaan website.
 * Hanya blok "form" pendaftaran anggota yang diganti; blok lain (FAQ, dll.) tidak disentuh.
 * Bisa dikembalikan kapan saja dari admin (Halaman → Daftar Anggota → Konten).
 */
return new class extends Migration
{
    public const FORM_URL = 'https://docs.google.com/forms/d/e/1FAIpQLSdqERlT-D7uhlWjWlvcO-KuzFbSDaG4djoAZwvpq6bvawqfeg/viewform';

    public function up(): void
    {
        $page = DB::table('pages')->where('slug', 'daftar')->first();
        if (! $page) {
            return;
        }

        $layout = json_decode($page->layout ?? '[]', true) ?: [];
        $changed = false;

        foreach ($layout as $key => $block) {
            if (($block['type'] ?? null) === 'form' && ($block['data']['formType'] ?? 'membership') === 'membership') {
                $layout[$key] = [
                    'type' => 'embed',
                    'data' => [
                        'introContent' => $block['data']['introContent'] ?? null,
                        'url' => self::FORM_URL,
                        'height' => 1400,
                        'buttonLabel' => 'Buka formulir di tab baru',
                    ],
                ];
                $changed = true;
            }
        }

        if ($changed) {
            DB::table('pages')->where('id', $page->id)->update(['layout' => json_encode($layout), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $page = DB::table('pages')->where('slug', 'daftar')->first();
        if (! $page) {
            return;
        }

        $layout = json_decode($page->layout ?? '[]', true) ?: [];
        foreach ($layout as $key => $block) {
            if (($block['type'] ?? null) === 'embed' && ($block['data']['url'] ?? null) === self::FORM_URL) {
                $layout[$key] = ['type' => 'form', 'data' => ['formType' => 'membership', 'introContent' => $block['data']['introContent'] ?? null]];
            }
        }
        DB::table('pages')->where('id', $page->id)->update(['layout' => json_encode($layout)]);
    }
};
