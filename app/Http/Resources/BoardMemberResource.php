<?php

namespace App\Http\Resources;

use App\Models\BoardMember;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BoardMember */
class BoardMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'position' => $this->position,
            'division' => $this->division,
            'divisionLabel' => BoardMember::DIVISIONS[$this->division] ?? $this->division,
            'photo' => Media::url($this->photo),
            'company' => $this->company,
            'bio' => $this->bio,
            'instagram' => $this->instagram,
            'linkedin' => $this->linkedin,
        ];
    }
}
