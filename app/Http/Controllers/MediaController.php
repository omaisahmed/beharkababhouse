<?php

namespace App\Http\Controllers;

use App\Services\MediaService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function __invoke(string $path, MediaService $media)
    {
        if (! $media->isSafePath($path) || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function branding(SettingsService $settings, MediaService $media)
    {
        $path = $settings->string('restaurant.logo_path');

        if ($path === '' || ! $media->isSafePath($path) || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
