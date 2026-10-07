<?php

namespace App\Support;

use App\Models\BoardMember;
use App\Models\Event;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Equivalent of Payload's `afterChange` revalidate hooks: tells the Next.js
 * frontend which cache tags to purge whenever content changes in the CMS.
 */
class FrontendRevalidator
{
    public static function register(): void
    {
        foreach ([Page::class, Post::class, Event::class, Program::class, BoardMember::class, Partner::class, Setting::class] as $model) {
            $model::saved(fn (Model $m) => static::queue($m));
            $model::deleted(fn (Model $m) => static::queue($m));
        }
    }

    public static function tagsFor(Model $model): array
    {
        return match (true) {
            $model instanceof Page => ['pages', "page:{$model->slug}"],
            $model instanceof Post => ['posts', "post:{$model->slug}", 'pages'],
            $model instanceof Event => ['events', "event:{$model->slug}", 'pages'],
            $model instanceof Program => ['programs', "program:{$model->slug}", 'pages'],
            $model instanceof BoardMember => ['board-members', 'pages'],
            $model instanceof Partner => ['partners', 'pages'],
            $model instanceof Setting && $model->key === MediaPaths::SETTING_KEY => ['globals', 'pages', 'posts', 'events', 'programs', 'board-members', 'partners'],
            $model instanceof Setting => ['globals'],
            default => [],
        };
    }

    protected static function queue(Model $model): void
    {
        $url = config('services.frontend.revalidate_url');
        $secret = config('services.frontend.revalidate_secret');
        $tags = static::tagsFor($model);

        if (! $url || ! $secret || ! $tags) {
            return;
        }

        dispatch(function () use ($url, $secret, $tags) {
            try {
                Http::timeout(5)->withHeaders(['x-revalidate-secret' => $secret])->post($url, ['tags' => $tags]);
            } catch (\Throwable $e) {
                Log::warning('Frontend revalidate failed: '.$e->getMessage());
            }
        })->afterResponse();
    }
}
