@extends('layouts.app')

@section('title', 'Kitchen')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Kitchen board</h1>
            <p class="page-subtitle">Live tickets for confirmed, preparing, and ready orders.</p>
        </div>
        <div class="page-actions">
            <button type="button" class="btn btn-ghost" onclick="window.location.reload()"><x-icon name="refresh" class="w-4 h-4" /> Refresh</button>
            <a href="{{ route('orders.index') }}" class="btn btn-ghost">All orders</a>
        </div>
    </div>

    @if ($orders->isEmpty())
        <div class="panel">
            <div class="empty" style="padding:70px 20px;">
                <div class="empty-icon"><x-icon name="kitchen" class="w-7 h-7" /></div>
                <div class="empty-title">Kitchen is clear</div>
                <div class="empty-text">New paid orders will appear here automatically after you refresh.</div>
            </div>
        </div>
    @else
        <div class="kitchen-grid">
            @foreach ($orders as $order)
                @php
                    $started = $order->confirmed_at ?? $order->created_at;
                    $minutes = (int) $started?->diffInMinutes(now());
                @endphp
                <article class="kitchen-card {{ $minutes >= $criticalMinutes ? 'overdue' : '' }}" data-started="{{ $started?->toIso8601String() }}" data-warning="{{ $warningMinutes }}" data-critical="{{ $criticalMinutes }}">
                    <div class="kitchen-head">
                        <div>
                            <div style="font-weight:800;font-size:15px;">{{ $order->order_number }}</div>
                            <div class="tiny">{{ $order->order_type?->label() }} @if ($order->table)· {{ $order->table->name }}@endif · {{ $order->user?->name }}</div>
                        </div>
                        <div style="text-align:right;">
                            <div class="badge {{ $order->status === \App\Enums\OrderStatus::Ready ? 'badge-emerald' : 'badge-amber' }}">{{ $order->status?->label() }}</div>
                            <div class="tiny" style="margin-top:4px;"><span data-elapsed>{{ $minutes }}m</span> ago</div>
                        </div>
                    </div>

                    <div class="kitchen-items">
                        @foreach ($order->items as $item)
                            <div class="kitchen-item">
                                <span class="kitchen-qty">{{ $item->quantity }}×</span>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:650;font-size:13px;">{{ $item->product_name }}</div>
                                    @if ($item->variant_name)<div class="tiny">{{ $item->variant_name }}</div>@endif
                                    @foreach ($item->modifiers as $modifier)
                                        <div class="tiny">+ {{ $modifier->name }}</div>
                                    @endforeach
                                    @if ($item->notes)<div class="tiny" style="color:var(--warning);font-weight:600;">* {{ $item->notes }}</div>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($order->notes)
                        <div class="alert alert-warning" style="margin:8px 0;padding:7px 9px;font-size:11px;">{{ $order->notes }}</div>
                    @endif

                    <div class="kitchen-foot">
                        @if ($order->status === \App\Enums\OrderStatus::Confirmed)
                            <form method="POST" action="{{ route('orders.status', $order) }}" style="flex:1;">
                                @csrf
                                <input type="hidden" name="status" value="preparing">
                                <button type="submit" class="btn btn-primary btn-sm" style="width:100%;">Start preparing</button>
                            </form>
                        @elseif ($order->status === \App\Enums\OrderStatus::Preparing)
                            <form method="POST" action="{{ route('orders.status', $order) }}" style="flex:1;">
                                @csrf
                                <input type="hidden" name="status" value="ready">
                                <button type="submit" class="btn btn-primary btn-sm" style="width:100%;">Mark ready</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('orders.status', $order) }}" style="flex:1;">
                                @csrf
                                <input type="hidden" name="status" value="served">
                                <button type="submit" class="btn btn-ghost btn-sm" style="width:100%;">Mark served</button>
                            </form>
                        @endif
                        <a href="{{ route('orders.kitchen-ticket', $order) }}" target="_blank" class="btn btn-ghost btn-sm"><x-icon name="printer" class="w-3.5 h-3.5" /></a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const cards = [...document.querySelectorAll('.kitchen-card')];

        const tick = () => {
            cards.forEach((card) => {
                const started = new Date(card.dataset.started);
                if (Number.isNaN(started.getTime())) return;
                const minutes = Math.max(0, Math.floor((Date.now() - started.getTime()) / 60000));
                const elapsed = card.querySelector('[data-elapsed]');
                if (elapsed) elapsed.textContent = minutes + 'm';
                card.classList.toggle('overdue', minutes >= Number(card.dataset.critical));
            });
        };

        tick();
        window.setInterval(tick, 30000);
    });
</script>
@endpush
