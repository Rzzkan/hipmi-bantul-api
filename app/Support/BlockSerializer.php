<?php

namespace App\Support;

use App\Http\Resources\EventResource;
use App\Http\Resources\PartnerResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\ProgramResource;
use App\Models\Event;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Program;

/**
 * Converts Filament Builder state ([{type, data}]) into the Payload CMS layout shape
 * ([{blockType, id, ...fields}]) and populates relationship blocks so the Next.js
 * frontend can render the page with a single request.
 */
class BlockSerializer
{
    public static function hero(?array $hero): array
    {
        $hero ??= [];

        return [
            'type' => $hero['type'] ?? 'lowImpact',
            'eyebrow' => $hero['eyebrow'] ?? null,
            'richText' => Html::clean($hero['richText'] ?? null),
            'media' => Media::url($hero['media'] ?? null),
            'links' => self::links($hero['links'] ?? []),
        ];
    }

    public static function layout(?array $layout): array
    {
        return collect($layout ?? [])
            ->map(fn ($block, $key) => self::block($block, is_string($key) ? $key : null))
            ->filter()
            ->values()
            ->all();
    }

    protected static function block(array $block, ?string $key): ?array
    {
        $type = $block['type'] ?? null;
        $data = $block['data'] ?? [];

        if (! $type) {
            return null;
        }

        $base = ['blockType' => $type, 'id' => $key ?? substr(md5(json_encode($block)), 0, 12)];

        $payload = match ($type) {
            'content' => [
                'background' => $data['background'] ?? 'default',
                'columns' => collect($data['columns'] ?? [])->map(fn ($col) => [
                    'size' => $col['size'] ?? 'full',
                    'richText' => Html::clean($col['richText'] ?? null),
                    'link' => ! empty($col['enableLink']) ? self::link($col['link'] ?? []) : null,
                ])->values()->all(),
            ],
            'mediaBlock' => [
                'media' => Media::url($data['media'] ?? null),
                'caption' => $data['caption'] ?? null,
            ],
            'cta' => [
                'richText' => Html::clean($data['richText'] ?? null),
                'links' => self::links($data['links'] ?? []),
            ],
            'stats' => [
                'introContent' => Html::clean($data['introContent'] ?? null),
                'items' => array_values($data['items'] ?? []),
            ],
            'faq' => [
                'introContent' => Html::clean($data['introContent'] ?? null),
                'items' => array_values($data['items'] ?? []),
            ],
            'archive' => self::archive($data),
            'team' => [
                'introContent' => Html::clean($data['introContent'] ?? null),
                'showInti' => (bool) ($data['showInti'] ?? true),
                'structure' => BoardStructure::build(
                    ($data['division_id'] ?? null) ? (int) $data['division_id'] : null,
                    (bool) ($data['showInti'] ?? true),
                ),
            ],
            'partners' => [
                'partners' => PartnerResource::collection(
                    Partner::active()->whereNotNull('logo')->where('logo', '!=', '')->when($data['tier'] ?? null, fn ($q, $t) => $q->where('tier', $t))->get()
                )->resolve(),
            ],
            'form' => [
                'formType' => $data['formType'] ?? 'membership',
                'introContent' => Html::clean($data['introContent'] ?? null),
                'successMessage' => $data['successMessage'] ?? 'Terima kasih! Data kamu sudah kami terima. Tim kami akan segera menghubungi.',
                'options' => self::formOptions($data['formType'] ?? 'membership'),
            ],
            'embed' => GoogleForm::isValid($data['url'] ?? null) ? [
                'provider' => 'google-form',
                'introContent' => Html::clean($data['introContent'] ?? null),
                'embedUrl' => GoogleForm::embedUrl($data['url']),
                'openUrl' => GoogleForm::openUrl($data['url']),
                'height' => max(400, min(5000, (int) ($data['height'] ?? 1400))),
                'buttonLabel' => $data['buttonLabel'] ?? 'Buka formulir di tab baru',
            ] : null,
            default => $data,
        };

        return $payload === null ? null : $base + $payload;
    }

    protected static function archive(array $data): array
    {
        $relationTo = $data['relationTo'] ?? 'posts';
        $limit = (int) ($data['limit'] ?? 3) ?: 3;
        $category = $data['category'] ?? null;

        $docs = match ($relationTo) {
            'events' => EventResource::collection(
                Event::published()->upcoming()->orderBy('start_at')->limit($limit)->get()
            ),
            'programs' => ProgramResource::collection(
                Program::published()->when($category, fn ($q) => $q->where('category', $category))
                    ->orderByDesc('is_featured')->orderBy('sort_order')->limit($limit)->get()
            ),
            default => PostResource::collection(
                Post::published()->when($category, fn ($q) => $q->where('category', $category))
                    ->latest('published_at')->limit($limit)->get()
            ),
        };

        return [
            'introContent' => Html::clean($data['introContent'] ?? null),
            'relationTo' => $relationTo,
            'limit' => $limit,
            'docs' => $docs->resolve(),
        ];
    }

    protected static function formOptions(string $formType): array
    {
        return match ($formType) {
            'event' => Event::published()->upcoming()->orderBy('start_at')->get()
                ->filter->acceptsRegistration()
                ->map(fn ($e) => ['value' => $e->id, 'label' => $e->title.' — '.$e->start_at->translatedFormat('d M Y')])
                ->values()->all(),
            'program' => Program::published()->where('registration_open', true)->orderBy('sort_order')->get()
                ->map(fn ($p) => ['value' => $p->id, 'label' => $p->title])->values()->all(),
            default => [],
        };
    }

    public static function links(?array $links): array
    {
        return collect($links ?? [])->map(fn ($l) => self::link($l))->filter()->values()->all();
    }

    public static function link(?array $link): ?array
    {
        if (empty($link['label']) || empty($link['url'])) {
            return null;
        }

        return [
            'label' => $link['label'],
            'url' => $link['url'],
            'appearance' => $link['appearance'] ?? 'default',
            'newTab' => (bool) ($link['newTab'] ?? false),
        ];
    }
}
