<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Support\Html;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'coverImage' => Media::url($this->cover_image),
            'startAt' => $this->start_at?->toIso8601String(),
            'endAt' => $this->end_at?->toIso8601String(),
            'location' => $this->location,
            'locationUrl' => $this->location_url,
            'price' => $this->price,
            'isFree' => $this->price === 0,
            'quota' => $this->quota,
            'seatsLeft' => $this->seatsLeft(),
            'registrationOpen' => $this->acceptsRegistration(),
            'description' => $this->when($request->routeIs('api.events.show'), fn () => Html::clean($this->description)),
            'meta' => [
                'title' => $this->title,
                'description' => $this->excerpt,
                'image' => Media::url($this->cover_image),
            ],
        ];
    }
}
