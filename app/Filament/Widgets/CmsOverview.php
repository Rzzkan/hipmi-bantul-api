<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\Post;
use App\Models\Registration;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CmsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $pending = Registration::where('status', 'pending')->count();
        $thisMonth = Registration::where('created_at', '>=', now()->startOfMonth())->count();
        $nextEvent = Event::published()->upcoming()->orderBy('start_at')->first();

        // Estimasi revenue dari pendaftar agenda berbayar yang sudah diterima
        $revenue = Registration::query()
            ->where('registrations.status', 'approved')
            ->join('events', 'events.id', '=', 'registrations.event_id')
            ->sum('events.price');

        return [
            Stat::make('Pendaftar menunggu', $pending)->description("{$thisMonth} pendaftar bulan ini")->color($pending ? 'warning' : 'success'),
            Stat::make('Agenda terdekat', $nextEvent?->title ?? '—')
                ->description($nextEvent ? $nextEvent->start_at->translatedFormat('l, d M Y H:i') : 'Belum ada agenda'),
            Stat::make('Estimasi pemasukan event', 'Rp '.number_format($revenue, 0, ',', '.'))->description('Dari peserta yang diterima'),
            Stat::make('Berita terbit', Post::published()->count()),
        ];
    }
}
