<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\SettingsService;
use Illuminate\View\View;

class KitchenController extends Controller
{
    public function __construct(protected SettingsService $settings) {}

    public function index(): View
    {
        $orders = Order::query()
            ->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Preparing->value, OrderStatus::Ready->value])
            ->with(['items.modifiers', 'table', 'user'])
            ->orderBy('confirmed_at')
            ->orderBy('created_at')
            ->get();

        return view('kitchen.index', [
            'orders' => $orders,
            'warningMinutes' => $this->settings->int('kitchen.warning_minutes', 10),
            'criticalMinutes' => $this->settings->int('kitchen.critical_minutes', 20),
        ]);
    }
}
