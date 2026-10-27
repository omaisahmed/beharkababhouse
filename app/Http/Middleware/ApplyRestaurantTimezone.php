<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyRestaurantTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $timezone = app(SettingsService::class)->timezone();
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        } catch (\Throwable) {
            // Keep the framework default when settings cannot be read yet.
        }

        return $next($request);
    }
}
