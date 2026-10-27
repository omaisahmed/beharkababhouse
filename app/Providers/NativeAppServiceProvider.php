<?php

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    public function boot(): void
    {
        Window::open()
            ->title('Behar Kabab House POS')
            ->width(1440)
            ->height(900)
            ->minWidth(1100)
            ->minHeight(700)
            ->hideMenu()
            ->rememberState()
            ->maximized();
    }

    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            'upload_max_filesize' => '10M',
            'post_max_size' => '12M',
        ];
    }
}
