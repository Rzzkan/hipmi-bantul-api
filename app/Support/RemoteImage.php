<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Downloads an image from a public URL so it can be compressed and stored in R2
 * (instead of hot-linking someone else's server, which can break or be slow).
 *
 * Guarded against SSRF: only http(s), only public IP addresses, redirects are
 * followed manually and re-checked, size & type are limited.
 */
class RemoteImage
{
    public const MAX_BYTES = ImageOptimizer::MAX_UPLOAD_KB * 1024;

    /** @var (\Closure(string): array<int, string>)|null  DNS resolver override (tests). */
    public static ?\Closure $resolveUsing = null;

    public static function fetch(string $url): string
    {
        $url = self::normalize(trim($url));

        for ($hop = 0; $hop <= 3; $hop++) {
            self::assertPublicUrl($url);

            $response = Http::timeout(20)
                ->withOptions(['allow_redirects' => false, 'stream' => false])
                ->withHeaders(['User-Agent' => 'HIPMI-Bantul-CMS/1.0 (+https://web.hipmibantul.com)', 'Accept' => 'image/*'])
                ->get($url);

            if ($response->redirect()) {
                $location = $response->header('Location');
                if (! $location) {
                    break;
                }
                $url = str_starts_with($location, 'http') ? $location : self::resolveRelative($url, $location);

                continue;
            }

            if (! $response->successful()) {
                throw new InvalidArgumentException("Gambar tidak bisa diunduh (HTTP {$response->status()}).");
            }

            $length = (int) $response->header('Content-Length');
            $body = $response->body();
            if ($length > self::MAX_BYTES || strlen($body) > self::MAX_BYTES) {
                throw new InvalidArgumentException('Ukuran gambar melebihi '.(self::MAX_BYTES / 1024 / 1024).' MB.');
            }

            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);
            if (! in_array($mime, ImageOptimizer::ACCEPTED_MIME, true)) {
                throw new InvalidArgumentException('URL tersebut bukan file gambar (JPG, PNG, WebP, atau GIF).');
            }

            return $body;
        }

        throw new InvalidArgumentException('Terlalu banyak redirect.');
    }

    /** Turn common "share" links into direct image links. */
    public static function normalize(string $url): string
    {
        // Google Drive: /file/d/{id}/view  →  direct download
        if (preg_match('~drive\.google\.com/(?:file/d/|open\?id=)([\w-]+)~', $url, $m)) {
            return "https://drive.google.com/uc?export=download&id={$m[1]}";
        }

        // Dropbox: ?dl=0 → ?raw=1
        if (str_contains($url, 'dropbox.com/')) {
            return preg_replace('~([?&])dl=0~', '$1raw=1', $url);
        }

        return $url;
    }

    public static function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('URL harus diawali http:// atau https://');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('URL tidak boleh berisi username/password.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : (self::$resolveUsing ? (self::$resolveUsing)($host) : (gethostbynamel($host) ?: []));
        if (! $ips) {
            throw new InvalidArgumentException('Domain gambar tidak ditemukan.');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('URL mengarah ke alamat jaringan internal dan tidak diizinkan.');
            }
        }
    }

    private static function resolveRelative(string $base, string $location): string
    {
        $p = parse_url($base);

        return $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '').'/'.ltrim($location, '/');
    }
}
