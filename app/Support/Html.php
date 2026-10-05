<?php

namespace App\Support;

use Illuminate\Support\Str;

class Html
{
    /** Sanitize rich-editor HTML before it leaves the API (XSS protection). */
    public static function clean(?string $html): ?string
    {
        return blank($html) ? null : Str::sanitizeHtml($html);
    }
}
