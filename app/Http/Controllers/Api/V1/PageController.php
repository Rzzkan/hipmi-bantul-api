<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PageResource;
use App\Models\Page;

class PageController extends Controller
{
    /** List published pages (for sitemap / generateStaticParams). */
    public function index()
    {
        return PageResource::collection(Page::published()->orderBy('title')->get());
    }

    public function show(string $slug)
    {
        return new PageResource(Page::published()->where('slug', $slug)->firstOrFail());
    }
}
