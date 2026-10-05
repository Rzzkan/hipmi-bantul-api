<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::published()
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('q'), fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest('published_at')
            ->paginate(min((int) $request->query('limit', 9), 50));

        return PostResource::collection($posts);
    }

    public function show(string $slug)
    {
        return new PostResource(Post::published()->where('slug', $slug)->firstOrFail());
    }
}
