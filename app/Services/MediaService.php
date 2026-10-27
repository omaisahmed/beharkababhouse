<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaService
{
    public const FOLDERS = ['branding', 'products', 'categories', 'expenses'];

    public function store(UploadedFile $file, string $folder): string
    {
        if (! in_array($folder, self::FOLDERS, true)) {
            throw ValidationException::withMessages(['file' => 'Unsupported upload location.']);
        }

        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $name = Str::uuid()->toString().'.'.$extension;

        Storage::disk('local')->putFileAs($folder, $file, $name);

        return $folder.'/'.$name;
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (! $this->isSafePath($path)) {
            return;
        }

        Storage::disk('local')->delete($path);
    }

    public function isSafePath(string $path): bool
    {
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '\\')) {
            return false;
        }

        $folder = explode('/', $path)[0];

        return in_array($folder, self::FOLDERS, true);
    }
}
