<?php

namespace App\Http\Resources;

use App\Models\Post;
use App\Support\Html;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'category' => $this->category,
            'categoryLabel' => Post::CATEGORIES[$this->category] ?? $this->category,
            'excerpt' => $this->excerpt,
            'coverImage' => Media::url($this->cover_image),
            'authorName' => $this->author_name,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'content' => $this->when($request->routeIs('api.posts.show'), fn () => Html::clean($this->content)),
            'meta' => [
                'title' => $this->meta_title ?: $this->title,
                'description' => $this->meta_description ?: $this->excerpt,
                'image' => Media::url($this->cover_image),
            ],
        ];
    }
}
