<?php

namespace App\Http\Controllers;

use App\Models\BackupHistory;
use App\Services\ReportService;
use App\Services\SettingsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports, SettingsService $settings): View
    {
        $data = $reports->dashboard();
        $shift = request()->user()->openShift();
        $lastBackup = BackupHistory::query()->latest()->first();
        $backupDue = $settings->bool('backup.auto_reminder', true)
            && (! $lastBackup || ($lastBackup->created_at && $lastBackup->created_at->diffInDays(now()) >= $settings->int('backup.reminder_days', 7)));

        return view('dashboard', array_merge($data, [
            'shift' => $shift,
            'lastBackup' => $lastBackup,
            'backupDue' => $backupDue,
        ]));
    }
}
