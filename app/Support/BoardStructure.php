<?php

namespace App\Support;

use App\Http\Resources\BoardMemberResource;
use App\Models\BoardMember;
use App\Models\Division;

/**
 * Builds the organisation chart: Pengurus Inti → Bidang → Kompartemen.
 */
class BoardStructure
{
    public static function build(?int $divisionId = null, bool $withInti = true): array
    {
        $members = BoardMember::active()->get();
        $present = fn ($collection) => BoardMemberResource::collection($collection->values())->resolve();

        $divisions = Division::query()
            ->where('is_active', true)
            ->when($divisionId, fn ($q) => $q->whereKey($divisionId))
            ->ordered()
            ->with('compartments')
            ->get()
            ->map(fn (Division $d) => [
                'id' => $d->id,
                'number' => $d->number,
                'name' => $d->name,
                'label' => $d->number ? "Bidang {$d->number}" : 'Bidang',
                'description' => $d->description,
                'leaders' => $present($members->where('level', BoardMember::LEVEL_BIDANG)->where('division_id', $d->id)),
                'compartments' => $d->compartments->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'members' => $present($members->where('level', BoardMember::LEVEL_KOMPARTEMEN)->where('compartment_id', $c->id)),
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'inti' => $withInti ? $present($members->where('level', BoardMember::LEVEL_INTI)) : [],
            'divisions' => $divisions,
        ];
    }
}
