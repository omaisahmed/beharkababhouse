<?php

namespace App\Http\Controllers;

use App\Models\BackupHistory;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(BackupService $backups): View
    {
        $this->authorize('manage-backups');

        $records = BackupHistory::query()->with('creator')->latest('id')->paginate(20);
        $latest = BackupHistory::query()->where('type', '!=', 'safety')->latest('id')->first();
        $lastBackup = BackupHistory::query()->where('type', '!=', 'safety')->latest()->first();

        return view('backups.index', [
            'backups' => $records,
            'directory' => $backups->directory(),
            'latest' => $latest,
            'stale' => ! $lastBackup || $lastBackup->created_at->lt(now()->subDays(7)),
        ]);
    }

    public function create(Request $request, BackupService $backups): RedirectResponse
    {
        $this->authorize('manage-backups');

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $backup = $backups->create($request->user(), 'manual', $data['notes'] ?? null);
        } catch (\Throwable $exception) {
            return back()->with('error', 'Backup failed: '.$exception->getMessage());
        }

        return back()->with('success', 'Backup created: '.$backup->filename);
    }

    public function download(BackupHistory $backup): BinaryFileResponse
    {
        $this->authorize('manage-backups');

        if (! File::exists($backup->path)) {
            abort(404, 'Backup file not found.');
        }

        return response()->download($backup->path, $backup->filename, ['Content-Type' => 'application/octet-stream']);
    }

    public function restore(Request $request, BackupHistory $backup, BackupService $backups): RedirectResponse
    {
        $this->authorize('manage-backups');

        $request->validate(['confirm' => ['accepted']]);

        try {
            $backups->restore($backup, $request->user());
        } catch (\Throwable $exception) {
            return back()->with('error', 'Restore failed: '.$exception->getMessage());
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Backup restored. Sign in again to continue.');
    }

    public function destroy(BackupHistory $backup, BackupService $backups): RedirectResponse
    {
        $this->authorize('manage-backups');

        $backups->delete($backup, request()->user());

        return back()->with('success', 'Backup deleted.');
    }
}
