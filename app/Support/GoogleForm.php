<?php

namespace App\Support;

/**
 * Helpers for embedding Google Forms safely (only docs.google.com/forms and forms.gle).
 */
class GoogleForm
{
    public const PATTERN = '~^https://(docs\.google\.com/forms/(?:u/\d+/)?d/(?:e/)?[\w-]+|forms\.gle/[\w-]+)~';

    public static function isValid(?string $url): bool
    {
        return (bool) preg_match(self::PATTERN, trim((string) $url));
    }

    /** Link to open the form normally (new tab). */
    public static function openUrl(string $url): string
    {
        $url = trim($url);
        if (str_contains($url, 'forms.gle/')) {
            return strtok($url, '?');
        }

        // drop query (e.g. ?usp=sharing / ?embedded=true) and normalise to /viewform
        $base = preg_replace('~/(viewform|edit|formResponse)$~', '', rtrim(strtok($url, '?'), '/'));

        return $base.'/viewform';
    }

    /** Iframe URL (Google's embed mode: no header/branding, fits in a page). */
    public static function embedUrl(string $url): string
    {
        $open = self::openUrl($url);

        // short links can't take query params reliably; Google redirects them to /viewform
        return str_contains($open, 'forms.gle/') ? $open : $open.'?embedded=true';
    }
}
