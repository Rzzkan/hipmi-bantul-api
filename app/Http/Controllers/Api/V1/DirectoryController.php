<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartnerResource;
use App\Models\Partner;
use App\Support\BoardStructure;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    /** Struktur pengurus: { inti: [...], divisions: [{ leaders, compartments: [{ members }] }] } */
    public function boardMembers(Request $request)
    {
        return response()->json([
            'data' => BoardStructure::build($request->integer('division') ?: null),
        ]);
    }

    public function partners()
    {
        return PartnerResource::collection(Partner::active()->get());
    }
}
