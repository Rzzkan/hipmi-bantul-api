<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\BlockSerializer;
use App\Support\Media;

/**
 * Payload-style globals: header, footer, site.
 */
class GlobalController extends Controller
{
    public const KEYS = ['site', 'header', 'footer'];

    public function index()
    {
        return response()->json(['data' => collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => $this->resolve($k)])]);
    }

    public function show(string $key)
    {
        abort_unless(in_array($key, self::KEYS, true), 404);

        return response()->json(['data' => $this->resolve($key)]);
    }

    protected function resolve(string $key): array
    {
        $value = Setting::get($key, []);

        return match ($key) {
            'site' => [
                'name' => $value['name'] ?? 'BPC HIPMI Bantul',
                'tagline' => $value['tagline'] ?? null,
                'logo' => Media::url($value['logo'] ?? null),
                'email' => $value['email'] ?? null,
                'phone' => $value['phone'] ?? null,
                'whatsapp' => $value['whatsapp'] ?? null,
                'address' => $value['address'] ?? null,
                'mapsUrl' => $value['maps_url'] ?? null,
                'socials' => array_values($value['socials'] ?? []),
                'defaultMetaImage' => Media::url($value['meta_image'] ?? null),
            ],
            'header' => [
                'navItems' => BlockSerializer::links($value['nav_items'] ?? []),
                'cta' => BlockSerializer::link($value['cta'] ?? null),
            ],
            'footer' => [
                'about' => $value['about'] ?? null,
                'navItems' => BlockSerializer::links($value['nav_items'] ?? []),
                'copyright' => $value['copyright'] ?? null,
            ],
        };
    }
}
