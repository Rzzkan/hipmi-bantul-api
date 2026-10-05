<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /** ?when=upcoming (default) | past | all */
    public function index(Request $request)
    {
        $when = $request->query('when', 'upcoming');

        $events = Event::published()
            ->when($when === 'upcoming', fn ($q) => $q->upcoming()->orderBy('start_at'))
            ->when($when === 'past', fn ($q) => $q->where('start_at', '<', now()->startOfDay())->orderByDesc('start_at'))
            ->when($when === 'all', fn ($q) => $q->orderByDesc('start_at'))
            ->paginate(min((int) $request->query('limit', 9), 50));

        return EventResource::collection($events);
    }

    public function show(string $slug)
    {
        return new EventResource(Event::published()->where('slug', $slug)->firstOrFail());
    }
}
