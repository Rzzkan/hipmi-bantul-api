<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BoardMemberResource;
use App\Http\Resources\PartnerResource;
use App\Models\BoardMember;
use App\Models\Partner;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    public function boardMembers(Request $request)
    {
        return BoardMemberResource::collection(
            BoardMember::active()->when($request->query('division'), fn ($q, $d) => $q->where('division', $d))->get()
        )->additional(['divisions' => BoardMember::DIVISIONS]);
    }

    public function partners()
    {
        return PartnerResource::collection(Partner::active()->get());
    }
}
