@extends('layouts.app')

@section('title', 'Backups')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Backup & restore</h1>
            <p class="page-subtitle">Create offline copies of the database and restore when needed.</p>
        </div>
        <div class="page-actions">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('backup-dialog').showModal()"><x-icon name="backup" class="w-4 h-4" /> Create backup</button>
        </div>
    </div>

    @if ($stale)
        <div class="alert alert-warning" style="margin-bottom:16px;"><x-icon name="alert" class="w-4 h-4 shrink-0" /><span>No recent backup found. Create one now to protect today's sales.</span></div>
    @endif

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Last backup</div><div class="stat-value" style="font-size:19px;">{{ $latest?->created_at?->format('d M Y, H:i') ?? 'Never' }}</div><div class="stat-meta">{{ $latest?->filename ?? 'Create your first backup' }}</div></div>
        <div class="stat"><div class="stat-label">Backups</div><div class="stat-value">{{ $backups->total() }}</div><div class="stat-meta">Stored on this device</div></div>
    </div>

    <div class="panel" style="margin-top:18px;">
        <div class="panel-head"><div><div class="panel-title">Stored backups</div><div class="panel-sub" style="word-break:break-all;">{{ $directory }}</div></div></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>File</th><th>Type</th><th class="r">Size</th><th>Created</th><th>Status</th><th class="r"></th></tr></thead>
                <tbody>
                    @forelse ($backups as $backup)
                        <tr>
                            <td><div style="font-weight:600;font-size:12px;word-break:break-all;">{{ $backup->filename }}</div><div class="tiny">{{ $backup->notes }}</div></td>
                            <td><span class="badge {{ $backup->type === 'safety' ? 'badge-amber' : 'badge-neutral' }}">{{ ucfirst($backup->type) }}</span></td>
                            <td class="r tiny">{{ number_format($backup->size_bytes / 1024, 0) }} KB</td>
                            <td class="tiny">{{ $backup->created_at?->format('d M Y, H:i') }}<div class="tiny">{{ $backup->creator?->name }}</div></td>
                            <td>
                                <span class="badge badge-emerald">{{ ucfirst($backup->status) }}</span>
                                @if ($backup->restored_at)<div class="tiny">Restored {{ $backup->restored_at->format('d M') }}</div>@endif
                            </td>
                            <td class="r">
                                <div style="display:flex;gap:5px;justify-content:flex-end;">
                                    <a href="{{ route('backups.download', $backup) }}" class="btn btn-ghost btn-sm">Download</a>
                                    <button type="button" class="btn btn-ghost btn-sm" data-restore="{{ $backup->id }}" data-file="{{ $backup->filename }}">Restore</button>
                                    <form method="POST" action="{{ route('backups.destroy', $backup) }}" data-confirm="Delete {{ $backup->filename }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty" style="padding:50px 20px;"><div class="empty-icon"><x-icon name="backup" class="w-7 h-7" /></div><div class="empty-title">No backups yet</div><div class="empty-text">Create a manual backup before closing the shop.</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($backups->hasPages())<div class="panel-body">{{ $backups->links() }}</div>@endif
    </div>

    <div class="panel" style="margin-top:18px;">
        <div class="panel-head"><div class="panel-title">How restore works</div></div>
        <div class="panel-body">
            <ol class="tiny" style="padding-left:18px;display:grid;gap:6px;">
                <li>A safety backup of the current database is created automatically.</li>
                <li>The selected backup replaces the live database after an integrity check.</li>
                <li>Everyone is signed out. Sign in again to continue.</li>
            </ol>
        </div>
    </div>
@endsection

@push('modals')
    <dialog class="modal" id="backup-dialog">
        <form method="POST" action="{{ route('backups.create') }}">
            @csrf
            <div class="modal-head"><div class="modal-title">Create backup</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
            <div class="modal-body">
                <p class="tiny" style="margin-bottom:12px;">The database is copied using SQLite <code>VACUUM INTO</code>, so the backup is consistent even while the app is open.</p>
                <div class="field"><label class="label">Notes (optional)</label><input name="notes" class="input" maxlength="500" placeholder="End of day, before update…"></div>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Create backup</button></div>
        </form>
    </dialog>

    <dialog class="modal" id="restore-dialog">
        <form method="POST" id="restore-form">
            @csrf
            <div class="modal-head"><div class="modal-title">Restore backup</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
            <div class="modal-body">
                <div class="alert alert-danger" style="margin-bottom:14px;"><x-icon name="alert" class="w-4 h-4 shrink-0" /><span>The live database will be replaced with <strong id="restore-file"></strong>. A safety backup is made first. You will be signed out.</span></div>
                <label class="check"><input type="checkbox" name="confirm" value="1" required><span>I understand and want to restore this backup</span></label>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-danger">Restore database</button></div>
        </form>
    </dialog>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-restore]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('restore-form').action = @json(url('/backups')) + '/' + button.dataset.restore + '/restore';
                document.getElementById('restore-file').textContent = button.dataset.file;
                document.getElementById('restore-dialog').showModal();
            });
        });
    });
</script>
@endpush
