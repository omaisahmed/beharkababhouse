<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\ReportService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): View
    {
        $this->authorize('view-reports');

        [$from, $to] = $this->range($request);

        return view('reports.overview', [
            'from' => $from,
            'to' => $to,
            'summary' => $reports->salesSummary($from, $to),
            'series' => $reports->dailySeries($from, $to),
            'topProducts' => $reports->topProducts($from, $to, 8),
            'payments' => $reports->paymentBreakdown($from, $to),
            'categories' => $reports->categoryBreakdown($from, $to),
            'staff' => $reports->staffPerformance($from, $to),
            'inventory' => $reports->inventorySnapshot(),
        ]);
    }

    public function sales(Request $request, ReportService $reports): View
    {
        $this->authorize('view-reports');

        [$from, $to] = $this->range($request);

        return view('reports.sales', [
            'from' => $from,
            'to' => $to,
            'summary' => $reports->salesSummary($from, $to),
            'series' => $reports->dailySeries($from, $to),
            'payments' => $reports->paymentBreakdown($from, $to),
        ]);
    }

    public function products(Request $request, ReportService $reports): View
    {
        $this->authorize('view-reports');

        [$from, $to] = $this->range($request);

        return view('reports.products', [
            'from' => $from,
            'to' => $to,
            'summary' => $reports->salesSummary($from, $to),
            'topProducts' => $reports->topProducts($from, $to, 50),
            'categories' => $reports->categoryBreakdown($from, $to),
        ]);
    }

    public function payments(Request $request, ReportService $reports): View
    {
        $this->authorize('view-reports');

        [$from, $to] = $this->range($request);

        return view('reports.payments', [
            'from' => $from,
            'to' => $to,
            'summary' => $reports->salesSummary($from, $to),
            'payments' => $reports->paymentBreakdown($from, $to),
        ]);
    }

    public function staff(Request $request, ReportService $reports): View
    {
        $this->authorize('view-reports');

        [$from, $to] = $this->range($request);

        return view('reports.staff', [
            'from' => $from,
            'to' => $to,
            'summary' => $reports->salesSummary($from, $to),
            'staff' => $reports->staffPerformance($from, $to),
        ]);
    }

    public function inventory(ReportService $reports): View
    {
        $this->authorize('view-reports');

        return view('reports.inventory', ['inventory' => $reports->inventorySnapshot()]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('view-reports');

        [$from, $to] = $this->range($request);

        $filename = 'sales-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($from, $to) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Order', 'Date', 'Type', 'Status', 'Payment', 'Subtotal', 'Discount', 'Service', 'Delivery', 'Tax', 'Total', 'Refunded', 'COGS', 'Cashier']);

            Order::query()
                ->with(['user', 'refunds'])
                ->sold()
                ->between($from, $to)
                ->orderBy('created_at')
                ->chunk(200, function ($orders) use ($handle) {
                    foreach ($orders as $order) {
                        fputcsv($handle, [
                            $order->order_number,
                            $order->created_at?->format('Y-m-d H:i'),
                            $order->order_type?->label(),
                            $order->status?->label(),
                            $order->payment_status?->label(),
                            Money::toDecimal($order->subtotal_minor),
                            Money::toDecimal($order->discount_minor),
                            Money::toDecimal($order->service_charge_minor),
                            Money::toDecimal($order->delivery_fee_minor),
                            Money::toDecimal($order->tax_minor),
                            Money::toDecimal($order->total_minor),
                            Money::toDecimal((int) $order->refunds->sum('amount_minor')),
                            Money::toDecimal($order->cogs_minor),
                            $order->user?->name,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function range(Request $request): array
    {
        $preset = $request->input('preset', 'month');
        $today = Carbon::today();

        [$from, $to] = match ($preset) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'yesterday' => [$today->copy()->subDay()->startOfDay(), $today->copy()->subDay()->endOfDay()],
            'week' => [$today->copy()->startOfWeek()->startOfDay(), $today->copy()->endOfDay()],
            'month' => [$today->copy()->startOfMonth()->startOfDay(), $today->copy()->endOfDay()],
            'year' => [$today->copy()->startOfYear()->startOfDay(), $today->copy()->endOfDay()],
            default => [Carbon::parse($request->input('from', $today->copy()->startOfMonth()))->startOfDay(), Carbon::parse($request->input('to', $today))->endOfDay()],
        };

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }
}
