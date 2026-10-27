@extends('layouts.app')

@section('title', 'Tables')

@php
    $money = app(\App\Services\SettingsService::class);
    $flat = collect($tables)->flatten(1);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Tables</h1>
            <p class="page-subtitle">{{ $flat->count() }} active tables · {{ $flat->where('status', \App\Enums\TableStatus::Occupied)->count() }} occupied</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('pos.index') }}" class="btn btn-ghost">Open POS</a>
            @can('manage-tables')
                <button type="button" class="btn btn-primary" onclick="document.getElementById('table-dialog').showModal()"><x-icon name="plus" class="w-4 h-4" /> Add table</button>
            @endcan
        </div>
    </div>

    @forelse ($tables as $zone => $zoneTables)
        <div style="margin-bottom:22px;">
            <div class="nav-group-label" style="color:var(--muted);padding:0 0 8px;font-size:11px;letter-spacing:.14em;">{{ strtoupper($zone) }}</div>
            <div class="table-grid">
                @foreach ($zoneTables as $table)
                    @php $active = $table->activeOrder; @endphp
                    <article class="table-card {{ $table->status === \App\Enums\TableStatus::Occupied ? 'occupied' : ($table->status === \App\Enums\TableStatus::Reserved ? 'reserved' : '') }}">
                        <div style="display:flex;align-items:flex-start;gap:8px;">
                            <div>
                                <div class="table-name">{{ $table->name }}</div>
                                <div class="table-meta">{{ $table->capacity }} seats · {{ $table->status?->label() }}</div>
                            </div>
                            @can('manage-tables')
                                <button type="button" class="icon-btn" style="margin-left:auto;" title="Edit table" data-edit-table="{{ $table->id }}" data-name="{{ $table->name }}" data-zone="{{ $table->zone }}" data-capacity="{{ $table->capacity }}" data-active="{{ $table->is_active ? 1 : 0 }}">
                                    <x-icon name="edit" class="w-3.5 h-3.5" />
                                </button>
                            @endcan
                        </div>

                        @if ($active)
                            <div style="flex:1;margin:11px 0;">
                                <a href="{{ route('orders.show', $active) }}" style="font-size:12px;font-weight:650;color:var(--brand-strong);">{{ $active->order_number }}</a>
                                <div class="tiny">{{ $active->items->sum('quantity') }} items · {{ $money->money($active->total_minor) }}</div>
                                <div class="tiny">Since {{ $active->created_at?->format('H:i') }}</div>
                            </div>
                        @else
                            <div style="flex:1;margin:11px 0;color:var(--muted);font-size:11.5px;">Table is available.</div>
                        @endif

                        <div class="table-card-foot">
                            @if ($active)
                                <a href="{{ route('orders.show', $active) }}" class="btn btn-ghost btn-sm" style="flex:1;">View check</a>
                            @else
                                <a href="{{ route('pos.index', ['table' => $table->id]) }}" class="btn btn-primary btn-sm" style="flex:1;">New order</a>
                            @endif

                            @can('manage-tables')
                                <details style="position:relative;">
                                    <summary class="icon-btn" style="list-style:none;cursor:pointer;"><x-icon name="settings" class="w-3.5 h-3.5" /></summary>
                                    <div class="dropdown" style="right:0;left:auto;min-width:170px;">
                                        @foreach (\App\Enums\TableStatus::cases() as $status)
                                            @if ($status !== $table->status)
                                                <form method="POST" action="{{ route('tables.status', $table) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="{{ $status->value }}">
                                                    <button class="dropdown-item" type="submit">Mark {{ strtolower($status->label()) }}</button>
                                                </form>
                                            @endif
                                        @endforeach
                                        <div class="dropdown-sep"></div>
                                        <form method="POST" action="{{ route('tables.destroy', $table) }}" data-confirm="Remove table {{ $table->name }}?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="dropdown-item danger" type="submit">Remove table</button>
                                        </form>
                                    </div>
                                </details>
                            @endcan
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    @empty
        <div class="panel">
            <div class="empty" style="padding:70px 20px;">
                <div class="empty-icon"><x-icon name="tables" class="w-7 h-7" /></div>
                <div class="empty-title">No tables configured</div>
                <div class="empty-text">Add your dining tables to start opening dine-in checks.</div>
            </div>
        </div>
    @endforelse
@endsection

@push('modals')
    @can('manage-tables')
        <dialog class="modal" id="table-dialog">
            <form method="POST" action="{{ route('tables.store') }}">
                @csrf
                <div class="modal-head"><div class="modal-title">Add table</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="field"><label class="label">Table name</label><input name="name" class="input" required maxlength="60" placeholder="Table 1"></div>
                        <div class="field"><label class="label">Zone</label><input name="zone" class="input" maxlength="60" placeholder="Main floor"></div>
                        <div class="field"><label class="label">Seats</label><input name="capacity" type="number" min="1" max="50" value="4" class="input" required></div>
                    </div>
                </div>
                <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Add table</button></div>
            </form>
        </dialog>

        <dialog class="modal" id="table-edit-dialog">
            <form method="POST" id="table-edit-form">
                @csrf
                @method('PUT')
                <div class="modal-head"><div class="modal-title">Edit table</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="field"><label class="label">Table name</label><input name="name" id="edit-table-name" class="input" required maxlength="60"></div>
                        <div class="field"><label class="label">Zone</label><input name="zone" id="edit-table-zone" class="input" maxlength="60"></div>
                        <div class="field"><label class="label">Seats</label><input name="capacity" id="edit-table-capacity" type="number" min="1" max="50" class="input" required></div>
                    </div>
                    <label class="check" style="margin-top:12px;"><input type="checkbox" name="is_active" value="1" id="edit-table-active"><span>Table is active</span></label>
                </div>
                <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
            </form>
        </dialog>
    @endcan
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-edit-table]').forEach((button) => {
            button.addEventListener('click', () => {
                const dialog = document.getElementById('table-edit-dialog');
                const form = document.getElementById('table-edit-form');
                form.action = @json(url('/tables')) + '/' + button.dataset.editTable;
                document.getElementById('edit-table-name').value = button.dataset.name || '';
                document.getElementById('edit-table-zone').value = button.dataset.zone || '';
                document.getElementById('edit-table-capacity').value = button.dataset.capacity || 1;
                document.getElementById('edit-table-active').checked = button.dataset.active === '1';
                dialog.showModal();
            });
        });
    });
</script>
@endpush
