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

        $inti = $withInti ? $members->where('level', BoardMember::LEVEL_INTI)->values() : collect();

        return [
            'inti' => $present($inti),
            'intiChart' => self::intiChart($inti, $present),
            'divisions' => $divisions,
        ];
    }

    /**
     * Groups Pengurus Inti for the org chart, based on the position title:
     *   Ketua Umum
     *   ├─ Sekretaris Umum  → Wakil Sekretaris (bisa lebih dari satu)
     *   └─ Bendahara        → Wakil Bendahara  (bisa lebih dari satu)
     * Anything else (custom titles) goes to "others".
     */
    public static function intiChart($inti, callable $present): array
    {
        $is = fn (BoardMember $m, string $word) => str_contains(mb_strtolower($m->position), $word);

        $sekretaris = $inti->filter(fn ($m) => $is($m, 'sekretaris'));
        $bendahara = $inti->filter(fn ($m) => $is($m, 'bendahara'))->diffKeys($sekretaris);
        $ketua = $inti->filter(fn ($m) => $is($m, 'ketua') && ! $is($m, 'wakil'))->diffKeys($sekretaris)->diffKeys($bendahara);
        $others = $inti->diffKeys($sekretaris)->diffKeys($bendahara)->diffKeys($ketua);

        $branch = fn ($group) => [
            'heads' => $present($group->reject(fn ($m) => $is($m, 'wakil'))),
            'deputies' => $present($group->filter(fn ($m) => $is($m, 'wakil'))),
        ];

        return [
            'ketua' => $present($ketua),
            'sekretaris' => $branch($sekretaris),
            'bendahara' => $branch($bendahara),
            'others' => $present($others),
        ];
    }
}
