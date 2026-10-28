<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Services\AuditService;
use App\Services\PosService;
use App\Services\RefundService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $query = Order::query()
            ->with(['table', 'user'])
            ->withCount('items')
            ->latest();

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('type')) {
            $query->where('order_type', $type);
        }

        if ($payment = $request->input('payment')) {
            $query->where('payment_status', $payment);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $orders = $query->paginate(20)->withQueryString();

        $stats = [
            'today_count' => Order::query()->whereDate('created_at', today())->count(),
            'today_sales' => (int) Order::query()->sold()->whereDate('created_at', today())->sum('total_minor'),
            'open_count' => Order::query()->active()->count(),
            'refunded_today' => (int) Order::query()->whereDate('created_at', today())->sum('refunded_minor'),
        ];

        return view('orders.index', compact('orders', 'stats'));
    }

    public function show(Order $order): View
    {
        $order->load(['items.modifiers', 'payments.user', 'refunds.user', 'table', 'user', 'shift', 'cancelledBy']);

        return view('orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order, AuditService $audit, PosService $pos): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['preparing', 'ready', 'served', 'completed'])],
        ]);

        if ($order->isCancelled()) {
            return back()->with('error', 'Cancelled orders cannot be updated.');
        }

        if ($order->status === OrderStatus::Completed) {
            return back()->with('error', 'Completed orders cannot be updated.');
        }

        $status = OrderStatus::from($data['status']);

        DB::transaction(function () use ($order, $status, $pos, $audit, $request) {
            $order->forceFill(['status' => $status]);

            if ($status === OrderStatus::Completed) {
                $order->completed_at = now();
            }

            $order->save();

            if ($status === OrderStatus::Completed) {
                $pos->releaseTable($order->table);
            }

            $audit->log(
                'order.status',
                'Order '.$order->order_number.' marked as '.$status->label().'.',
                $order,
                ['status' => $status->value],
                $request->user(),
            );
        });

        return back()->with('success', 'Order marked as '.$status->label().'.');
    }

    public function void(Request $request, Order $order, RefundService $refunds): RedirectResponse
    {
        $this->authorize('void-orders');

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $refunds->void($order, $request->user(), $data['reason']);

        return redirect()->route('orders.show', $order)->with('success', 'Order voided and stock restored.');
    }

    public function refund(Request $request, Order $order, RefundService $refunds): RedirectResponse
    {
        $this->authorize('process-refunds');

        $data = $request->validate([
            'amount' => ['required', 'string', 'max:20'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'restock' => ['nullable', 'boolean'],
        ]);

        try {
            $amount = Money::parse($data['amount']);
        } catch (\InvalidArgumentException) {
            return back()->withErrors(['amount' => 'Enter a valid refund amount.'])->withInput();
        }

        $refunds->refund(
            $order,
            $request->user(),
            $amount,
            PaymentMethod::from($data['method']),
            $data['reason'],
            (bool) ($data['restock'] ?? false),
        );

        return redirect()->route('orders.show', $order)->with('success', 'Refund recorded.');
    }
}
