<?php

namespace App\Http\Middleware;

use App\Services\InstallationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationIsInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = app(InstallationService::class)->isInstalled();

        if (! $installed && ! $request->routeIs('setup.*')) {
            return redirect()->route('setup.create');
        }

        if ($installed && $request->routeIs('setup.*')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
