@extends('layouts.app')

@section('title', 'Modifiers')

@php
    $money = app(\App\Services\SettingsService::class);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Modifier groups</h1>
            <p class="page-subtitle">Add-ons such as extra cheese, spice level, or drink size.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('products.index') }}" class="btn btn-ghost">Products</a>
            <a href="{{ route('modifier-groups.create') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> New group</a>
        </div>
    </div>

    @if ($groups->isEmpty())
        <div class="panel">
            <div class="empty" style="padding:70px 20px;">
                <div class="empty-icon"><x-icon name="modifiers" class="w-7 h-7" /></div>
                <div class="empty-title">No modifier groups</div>
                <div class="empty-text">Create a group like “Spice level” with mild, medium, and hot options.</div>
                <a href="{{ route('modifier-groups.create') }}" class="btn btn-primary" style="margin-top:14px;">Create a group</a>
            </div>
        </div>
    @else
        <div class="grid-2" style="align-items:start;">
            @foreach ($groups as $group)
                <div class="panel">
                    <div class="panel-head">
                        <div>
                            <div class="panel-title">{{ $group->name }}</div>
                            <div class="panel-sub">
                                {{ $group->modifiers->count() }} options · {{ $group->products_count }} products
                                · select {{ $group->min_selections }}–{{ $group->max_selections }}
                                @if ($group->is_required) · required @endif
                            </div>
                        </div>
                        <span class="badge {{ $group->is_active ? 'badge-emerald' : 'badge-neutral' }}">{{ $group->is_active ? 'Active' : 'Hidden' }}</span>
                    </div>
                    <div class="panel-body">
                        @if ($group->description)<p class="tiny" style="margin-bottom:10px;">{{ $group->description }}</p>@endif
                        @forelse ($group->modifiers as $modifier)
                            <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--border);">
                                <div style="flex:1;">
                                    <div style="font-size:12.5px;font-weight:600;">{{ $modifier->name }}</div>
                                    @if (! $modifier->is_active)<div class="tiny">Hidden</div>@endif
                                </div>
                                <span class="tiny" style="font-weight:600;">{{ $modifier->price_minor ? '+ '.$money->money($modifier->price_minor) : 'Free' }}</span>
                            </div>
                        @empty
                            <div class="tiny">No options yet.</div>
                        @endforelse
                    </div>
                    <div class="panel-body" style="border-top:1px solid var(--border);display:flex;gap:7px;justify-content:flex-end;">
                        <a href="{{ route('modifier-groups.edit', $group) }}" class="btn btn-ghost btn-sm">Edit</a>
                        <form method="POST" action="{{ route('modifier-groups.destroy', $group) }}" data-confirm="Delete group {{ $group->name }}? Products will lose these add-ons.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">Delete</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
