<?php

namespace App\Http\Resources;

use App\Models\BoardMember;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin BoardMember */
class BoardMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'position' => $this->position,
            'level' => $this->level,
            'divisionId' => $this->division_id,
            'compartmentId' => $this->compartment_id,
            'photo' => Media::url($this->photo),
            'company' => $this->company,
            'bio' => $this->bio,
            'socials' => array_filter([
                'instagram' => self::social($this->instagram, 'https://instagram.com/'),
                'tiktok' => self::social($this->tiktok, 'https://www.tiktok.com/@'),
                'linkedin' => self::social($this->linkedin, 'https://www.linkedin.com/in/'),
            ]),
        ];
    }

    /**
     * Admin boleh isi "@username", "username", atau URL lengkap — semuanya dinormalisasi.
     *
     * @return array{handle: string, url: string}|null
     */
    public static function social(?string $value, string $base): ?array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            $path = trim((string) parse_url($value, PHP_URL_PATH), '/');
            $handle = Str::afterLast($path, '/') ?: $path;

            return ['handle' => ltrim($handle, '@'), 'url' => $value];
        }

        $handle = ltrim($value, '@');

        return ['handle' => $handle, 'url' => $base.$handle];
    }
}
