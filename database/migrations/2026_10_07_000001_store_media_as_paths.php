<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * One-time cleanup: image URLs that were saved in full (e.g. https://media.hipmibantul.com/cms/posts/a.webp)
 * become paths (posts/a.webp). External images are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('media:normalize-urls');
    }

    public function down(): void
    {
        // Paths work with any base URL; nothing to restore.
    }
};
