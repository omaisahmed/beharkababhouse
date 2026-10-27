<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function salesSummary(CarbonInterface $from, CarbonInterface $to): array
    {
        $orders = Order::query()->sold()->between($from, $to);

        $totals = (clone $orders)
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_minor),0) as total_minor, COALESCE(SUM(net_minor),0) as net_minor, COALESCE(SUM(tax_minor),0) as tax_minor, COALESCE(SUM(service_charge_minor),0) as service_charge_minor, COALESCE(SUM(delivery_fee_minor),0) as delivery_fee_minor, COALESCE(SUM(discount_minor),0) as discount_minor, COALESCE(SUM(cogs_minor),0) as cogs_minor, COALESCE(SUM(gross_minor),0) as gross_minor')
            ->first();

        $refunds = (int) Refund::query()
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount_minor');

        $refundCount = (int) Refund::query()->whereBetween('created_at', [$from, $to])->count();

        $items = (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereIn('orders.payment_status', [
                PaymentStatus::Paid->value,
                PaymentStatus::PartiallyRefunded->value,
                PaymentStatus::Refunded->value,
            ])
            ->sum('order_items.quantity');

        $orderCount = (int) $totals->order_count;
        $total = (int) $totals->total_minor;
        $netSales = max(0, $total - $refunds);
        $cogs = (int) $totals->cogs_minor;

        return [
            'order_count' => $orderCount,
            'items_sold' => $items,
            'gross_minor' => (int) $totals->gross_minor,
            'net_minor' => (int) $totals->net_minor,
            'discount_minor' => (int) $totals->discount_minor,
            'tax_minor' => (int) $totals->tax_minor,
            'service_charge_minor' => (int) $totals->service_charge_minor,
            'delivery_fee_minor' => (int) $totals->delivery_fee_minor,
            'total_minor' => $total,
            'refunds_minor' => $refunds,
            'refund_count' => $refundCount,
            'net_sales_minor' => $netSales,
            'cogs_minor' => $cogs,
            'gross_profit_minor' => $netSales - $cogs,
            'average_ticket_minor' => $orderCount > 0 ? intdiv($netSales, $orderCount) : 0,
        ];
    }

    public function dailySeries(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $orders = Order::query()
            ->sold()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('date(created_at) as day, COUNT(*) as orders, COALESCE(SUM(total_minor),0) as total, COALESCE(SUM(cogs_minor),0) as cogs')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        $refunds = Refund::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('date(created_at) as day, COALESCE(SUM(amount_minor),0) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $key = $day->toDateString();
            $row = $orders->get($key);
            $refund = (int) ($refunds[$key] ?? 0);
            $total = (int) ($row->total ?? 0) - $refund;

            $series[] = [
                'date' => $key,
                'label' => $day->format('d M'),
                'orders' => (int) ($row->orders ?? 0),
                'total_minor' => $total,
                'cogs_minor' => (int) ($row->cogs ?? 0),
                'profit_minor' => $total - (int) ($row->cogs ?? 0),
            ];
        }

        return $series;
    }

    public function paymentBreakdown(CarbonInterface $from, CarbonInterface $to): array
    {
        $payments = Payment::query()
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('method, COUNT(*) as count, COALESCE(SUM(amount_minor),0) as total')
            ->groupBy('method')
            ->get()
            ->keyBy('method');

        $refunds = Refund::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('method, COUNT(*) as count, COALESCE(SUM(amount_minor),0) as total')
            ->groupBy('method')
            ->get()
            ->keyBy('method');

        return collect(PaymentMethod::cases())->map(function (PaymentMethod $method) use ($payments, $refunds) {
            $payment = $payments->get($method->value);
            $refund = $refunds->get($method->value);

            return [
                'method' => $method->value,
                'label' => $method->label(),
                'count' => (int) ($payment->count ?? 0),
                'total_minor' => (int) ($payment->total ?? 0),
                'refund_count' => (int) ($refund->count ?? 0),
                'refund_minor' => (int) ($refund->total ?? 0),
                'net_minor' => (int) ($payment->total ?? 0) - (int) ($refund->total ?? 0),
            ];
        })->all();
    }

    public function topProducts(CarbonInterface $from, CarbonInterface $to, int $limit = 12): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereIn('orders.payment_status', [
                PaymentStatus::Paid->value,
                PaymentStatus::PartiallyRefunded->value,
                PaymentStatus::Refunded->value,
            ])
            ->selectRaw('order_items.product_name, COALESCE(SUM(order_items.quantity),0) as quantity, COALESCE(SUM(order_items.line_net_minor),0) as revenue')
            ->groupBy('order_items.product_name')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->product_name,
                'quantity' => (int) $row->quantity,
                'revenue_minor' => (int) $row->revenue,
            ])
            ->all();
    }

    public function categoryBreakdown(CarbonInterface $from, CarbonInterface $to): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereIn('orders.payment_status', [
                PaymentStatus::Paid->value,
                PaymentStatus::PartiallyRefunded->value,
                PaymentStatus::Refunded->value,
            ])
            ->selectRaw("COALESCE(order_items.category_name, 'Uncategorised') as category, COALESCE(SUM(order_items.quantity),0) as quantity, COALESCE(SUM(order_items.line_net_minor),0) as revenue")
            ->groupBy('category')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->category,
                'quantity' => (int) $row->quantity,
                'revenue_minor' => (int) $row->revenue,
            ])
            ->all();
    }

    public function staffPerformance(CarbonInterface $from, CarbonInterface $to): array
    {
        return DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereIn('orders.payment_status', [
                PaymentStatus::Paid->value,
                PaymentStatus::PartiallyRefunded->value,
                PaymentStatus::Refunded->value,
            ])
            ->selectRaw('users.name, users.role, COUNT(*) as order_count, COALESCE(SUM(orders.total_minor),0) as total')
            ->groupBy('users.id', 'users.name', 'users.role')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'role' => $row->role,
                'order_count' => (int) $row->order_count,
                'total_minor' => (int) $row->total,
            ])
            ->all();
    }

    public function inventorySnapshot(): array
    {
        $items = InventoryItem::query()->orderBy('name')->get();

        return [
            'item_count' => $items->count(),
            'low_stock_count' => $items->filter->isLowStock()->count(),
            'out_of_stock_count' => $items->filter(fn (InventoryItem $item) => $item->stock_milli <= 0)->count(),
            'stock_value_minor' => (int) $items->sum(fn (InventoryItem $item) => $item->stockValueMinor()),
            'items' => $items,
        ];
    }

    public function dashboard(): array
    {
        $today = Carbon::today();
        $summary = $this->salesSummary($today->copy()->startOfDay(), $today->copy()->endOfDay());

        $openOrders = Order::query()->active()->count();
        $heldOrders = Order::query()->whereIn('status', [OrderStatus::Held->value, OrderStatus::Draft->value])->count();
        $kitchenOrders = Order::query()->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Preparing->value, OrderStatus::Ready->value])->count();

        $lowStock = InventoryItem::query()
            ->where('minimum_stock_milli', '>', 0)
            ->whereColumn('stock_milli', '<=', 'minimum_stock_milli')
            ->orderBy('stock_milli')
            ->limit(6)
            ->get();

        $recentOrders = Order::query()
            ->with(['items', 'table', 'user'])
            ->latest()
            ->limit(8)
            ->get();

        $topProducts = $this->topProducts($today->copy()->startOfDay(), $today->copy()->endOfDay(), 5);
        $series = $this->dailySeries($today->copy()->subDays(6)->startOfDay(), $today->copy()->endOfDay());

        return compact('summary', 'openOrders', 'heldOrders', 'kitchenOrders', 'lowStock', 'recentOrders', 'topProducts', 'series');
    }
}
