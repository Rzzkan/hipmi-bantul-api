<?php

namespace App\Http\Resources;

use App\Models\Page;
use App\Support\BlockSerializer;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Page */
class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detail = $request->routeIs('api.pages.show');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'hero' => $this->when($detail, fn () => BlockSerializer::hero($this->hero)),
            'layout' => $this->when($detail, fn () => BlockSerializer::layout($this->layout)),
            'meta' => [
                'title' => $this->meta_title ?: $this->title,
                'description' => $this->meta_description,
                'image' => Media::url($this->meta_image),
            ],
        ];
    }
}
