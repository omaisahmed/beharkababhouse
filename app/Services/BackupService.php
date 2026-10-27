<?php

namespace App\Services;

use App\Models\BackupHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class BackupService
{
    public function __construct(
        protected SettingsService $settings,
        protected AuditService $audit,
    ) {}

    public function directory(): string
    {
        $configured = trim($this->settings->string('backup.directory'));

        return $configured !== '' ? $configured : storage_path('app/backups');
    }

    public function create(?User $user = null, string $type = 'manual', ?string $notes = null): BackupHistory
    {
        $directory = $this->directory();

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $database = DB::connection()->getDatabaseName();

        if (! $database || ! File::exists($database)) {
            throw new RuntimeException('The database file could not be located.');
        }

        $filename = 'behar-pos-'.now()->format('Ymd-His').'-'.strtolower($type).'.sqlite';
        $path = rtrim($directory, '\\/').DIRECTORY_SEPARATOR.$filename;

        if (File::exists($path)) {
            $filename = 'behar-pos-'.now()->format('Ymd-His').'-'.strtolower($type).'-'.random_int(100, 999).'.sqlite';
            $path = rtrim($directory, '\\/').DIRECTORY_SEPARATOR.$filename;
        }

        try {
            DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (\Throwable) {
            //
        }

        $escaped = str_replace("'", "''", $path);
        DB::statement("VACUUM INTO '{$escaped}'");

        if (! File::exists($path)) {
            throw new RuntimeException('The backup file was not created.');
        }

        $record = BackupHistory::query()->create([
            'filename' => $filename,
            'path' => $path,
            'size_bytes' => File::size($path),
            'checksum' => hash_file('sha256', $path),
            'type' => $type,
            'status' => 'completed',
            'notes' => $notes,
            'created_by' => $user?->id,
        ]);

        $this->audit->log(
            'backup.created',
            'Created '.$type.' backup '.$filename.'.',
            $record,
            ['path' => $path, 'size_bytes' => $record->size_bytes],
            $user,
        );

        return $record;
    }

    public function restore(BackupHistory $backup, User $user): BackupHistory
    {
        if (! File::exists($backup->path)) {
            throw new RuntimeException('The backup file no longer exists.');
        }

        if (! $this->isValidSqlite($backup->path)) {
            throw new RuntimeException('This file is not a valid, healthy SQLite database.');
        }

        $database = DB::connection()->getDatabaseName();

        if (! $database || ! File::exists($database)) {
            throw new RuntimeException('The active database file could not be located.');
        }

        $this->create($user, 'safety', 'Automatic safety copy created before restoring '.$backup->filename.'.');

        $this->audit->log(
            'backup.restore_started',
            'Started restoring backup '.$backup->filename.'.',
            $backup,
            ['path' => $backup->path],
            $user,
        );

        try {
            DB::disconnect();
            DB::purge();

            File::copy($backup->path, $database.'._restore');

            foreach ([$database, $database.'-wal', $database.'-shm'] as $file) {
                if (File::exists($file)) {
                    File::delete($file);
                }
            }

            if (! File::copy($database.'._restore', $database)) {
                throw new RuntimeException('The database file could not be replaced.');
            }

            File::delete($database.'._restore');
        } catch (\Throwable $exception) {
            @File::delete($database.'._restore');
            DB::reconnect();

            throw new RuntimeException('Restore failed: '.$exception->getMessage(), 0, $exception);
        }

        DB::reconnect();

        try {
            DB::statement('PRAGMA foreign_keys = ON');
            DB::statement('PRAGMA journal_mode = WAL');
        } catch (\Throwable) {
            //
        }

        $record = BackupHistory::query()->updateOrCreate(
            ['filename' => $backup->filename],
            [
                'path' => $backup->path,
                'size_bytes' => File::exists($backup->path) ? File::size($backup->path) : $backup->size_bytes,
                'checksum' => File::exists($backup->path) ? hash_file('sha256', $backup->path) : $backup->checksum,
                'type' => $backup->type,
                'status' => 'completed',
                'notes' => trim(($backup->notes ?? '').' Restored on '.now()->format('Y-m-d H:i').'.'),
                'created_by' => $backup->created_by,
                'restored_at' => now(),
            ],
        );

        $this->audit->log(
            'backup.restored',
            'Restored backup '.$backup->filename.'.',
            $record,
            ['path' => $backup->path],
            $user,
        );

        return $record;
    }

    public function delete(BackupHistory $backup, User $user): void
    {
        if (File::exists($backup->path)) {
            File::delete($backup->path);
        }

        $this->audit->log(
            'backup.deleted',
            'Deleted backup '.$backup->filename.'.',
            null,
            ['path' => $backup->path],
            $user,
        );

        $backup->delete();
    }

    public function isValidSqlite(string $path): bool
    {
        try {
            $pdo = new \PDO('sqlite:'.$path);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $result = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            $hasUsers = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn();

            return $result === 'ok' && $hasUsers !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
