<?php

namespace App\Providers;

use App\Support\FrontendRevalidator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('registrations', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        FrontendRevalidator::register();
    }
}
