<?php

namespace App\Http\Controllers;

use App\Models\BackupHistory;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class AboutController extends Controller
{
    public function __invoke(SettingsService $settings): View
    {
        $database = DB::connection()->getDatabaseName();
        $databaseSize = $database && File::exists($database) ? File::size($database) : 0;
        $lastBackup = BackupHistory::query()->latest()->first();

        return view('about', [
            'version' => $settings->string('app.version', '0.1.0'),
            'restaurant' => $settings->restaurantName(),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'sqlite' => DB::selectOne('select sqlite_version() as version')?->version,
            'databaseSize' => $databaseSize,
            'lastBackup' => $lastBackup,
            'storageFree' => @disk_free_space(storage_path()) ?: 0,
            'settings' => $settings,
        ]);
    }
}
