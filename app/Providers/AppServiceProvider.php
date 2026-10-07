<?php

namespace App\Providers;

use App\Support\FrontendRevalidator;
use App\Support\MediaPaths;
use App\Support\R2Filesystem;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
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

        Storage::extend('r2', fn ($app, array $config) => R2Filesystem::make($config));

        // Base URL R2 dapat diatur dari admin (Pengaturan Situs → Media).
        MediaPaths::applyConfiguredBaseUrl();
    }
}
