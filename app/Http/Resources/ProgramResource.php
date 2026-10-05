<?php

namespace App\Http\Resources;

use App\Models\Program;
use App\Support\Html;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Program */
class ProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'category' => $this->category,
            'categoryLabel' => Program::CATEGORIES[$this->category] ?? $this->category,
            'summary' => $this->summary,
            'coverImage' => Media::url($this->cover_image),
            'benefits' => collect($this->benefits ?? [])->pluck('item')->filter()->values(),
            'price' => $this->price,
            'isFree' => $this->price === 0,
            'registrationOpen' => $this->registration_open,
            'isFeatured' => $this->is_featured,
            'description' => $this->when($request->routeIs('api.programs.show'), fn () => Html::clean($this->description)),
            'meta' => [
                'title' => $this->title,
                'description' => $this->summary,
                'image' => Media::url($this->cover_image),
            ],
        ];
    }
}
