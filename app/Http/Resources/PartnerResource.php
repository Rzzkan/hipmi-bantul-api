<?php

namespace App\Http\Resources;

use App\Models\Partner;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Partner */
class PartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo' => Media::url($this->logo),
            'url' => $this->url,
            'tier' => $this->tier,
        ];
    }
}
