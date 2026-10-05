<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index(Request $request)
    {
        $programs = Program::published()
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->orderByDesc('is_featured')->orderBy('sort_order')
            ->get();

        return ProgramResource::collection($programs);
    }

    public function show(string $slug)
    {
        return new ProgramResource(Program::published()->where('slug', $slug)->firstOrFail());
    }
}
