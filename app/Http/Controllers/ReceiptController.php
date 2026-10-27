<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function __construct(protected SettingsService $settings) {}

    public function show(Request $request, Order $order): View
    {
        $order->load(['items.modifiers', 'payments', 'refunds', 'table', 'user']);

        return view('receipts.order', [
            'order' => $order,
            'width' => $this->settings->int('receipt.width_mm', 80),
            'autoprint' => $request->boolean('autoprint'),
        ]);
    }

    public function kitchen(Order $order): View
    {
        $order->load(['items.modifiers', 'table', 'user']);

        return view('receipts.kitchen', [
            'order' => $order,
            'width' => $this->settings->int('receipt.width_mm', 80),
        ]);
    }

    public function test(): View
    {
        return view('receipts.test', [
            'width' => $this->settings->int('receipt.width_mm', 80),
        ]);
    }
}
