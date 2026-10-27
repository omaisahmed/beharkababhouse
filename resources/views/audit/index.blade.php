@extends('layouts.app')

@section('title', 'Audit log')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Audit log</h1>
            <p class="page-subtitle">A local record of important actions taken in the app.</p>
        </div>
    </div>

    <div class="panel">
        <form method="GET" action="{{ route('audit.index') }}" class="panel-body" style="display:flex;gap:9px;flex-wrap:wrap;align-items:flex-end;border-bottom:1px solid var(--border);">
            <div class="field" style="min-width:220px;"><label class="label">Search</label><input name="q" value="{{ request('q') }}" class="input" placeholder="Description or action…"></div>
            <div class="field" style="min-width:170px;"><label class="label">User</label><select name="user" class="select"><option value="">All users</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected(request('user') == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
            <div class="field" style="min-width:200px;"><label class="label">Action</label><select name="action" class="select"><option value="">All actions</option>@foreach ($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>@endforeach</select></div>
            <div class="field"><label class="label">From</label><input type="date" name="from" value="{{ request('from') }}" class="input"></div>
            <div class="field"><label class="label">To</label><input type="date" name="to" value="{{ request('to') }}" class="input"></div>
            <button type="submit" class="btn btn-ghost">Filter</button>
            @if (request()->hasAny(['q', 'user', 'action', 'from', 'to']))<a href="{{ route('audit.index') }}" class="btn btn-ghost">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>When</th><th>Action</th><th>Description</th><th>User</th><th>IP</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="tiny" style="white-space:nowrap;">{{ $log->created_at?->format('d M Y') }}<div class="tiny">{{ $log->created_at?->format('H:i:s') }}</div></td>
                            <td><span class="badge badge-neutral">{{ $log->action }}</span></td>
                            <td>
                                <div style="font-size:12.5px;">{{ $log->description }}</div>
                                @if ($log->meta)
                                    <details style="margin-top:4px;">
                                        <summary class="tiny" style="cursor:pointer;">Details</summary>
                                        <pre class="tiny" style="white-space:pre-wrap;word-break:break-all;margin-top:4px;color:var(--muted);">{{ json_encode($log->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                @endif
                                @if ($log->subject_type)
                                    <div class="tiny">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</div>
                                @endif
                            </td>
                            <td class="tiny">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="tiny">{{ $log->ip_address ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty" style="padding:50px 20px;"><div class="empty-icon"><x-icon name="audit" class="w-7 h-7" /></div><div class="empty-title">No audit entries</div><div class="empty-text">Actions will be recorded as staff use the app.</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())<div class="panel-body">{{ $logs->links() }}</div>@endif
    </div>
@endsection
