<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\Pos\HoldOrderRequest;
use App\Http\Requests\Pos\StoreOrderRequest;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Services\PosService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        protected PosService $pos,
        protected SettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $categories = Category::query()
            ->active()
            ->ordered()
            ->withCount(['activeProducts'])
            ->get();

        $products = Product::query()
            ->available()
            ->with([
                'category:id,name',
                'variants' => fn ($query) => $query->where('is_active', true),
                'modifierGroups' => fn ($query) => $query->where('is_active', true),
                'modifierGroups.modifiers' => fn ($query) => $query->where('is_active', true),
            ])
            ->ordered()
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category_id' => $product->category_id,
                'category' => $product->category?->name,
                'price_minor' => $product->price_minor,
                'image' => $product->image_path ? route('media', ['path' => $product->image_path]) : null,
                'tax_rate_bp' => $product->tax_rate_bp ?? ($this->settings->bool('tax.enabled') ? $this->settings->int('tax.rate_bp') : 0),
                'is_taxable' => $product->is_taxable,
                'kitchen_station' => $product->kitchen_station,
                'variants' => $product->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'price_delta_minor' => $variant->price_delta_minor,
                    'is_default' => $variant->is_default,
                ])->values()->all(),
                'modifier_groups' => $product->modifierGroups->map(fn ($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'is_required' => $group->is_required,
                    'min_selections' => $group->min_selections,
                    'max_selections' => $group->max_selections,
                    'modifiers' => $group->modifiers->map(fn ($modifier) => [
                        'id' => $modifier->id,
                        'name' => $modifier->name,
                        'price_minor' => $modifier->price_minor,
                    ])->values()->all(),
                ])->values()->all(),
            ]);

        $tables = $this->settings->bool('tables.enabled', true)
            ? RestaurantTable::query()->active()->ordered()->get()->map(fn (RestaurantTable $table) => [
                'id' => $table->id,
                'name' => $table->name,
                'zone' => $table->zone,
                'capacity' => $table->capacity,
                'status' => $table->status->value,
            ])->all()
            : [];

        $resume = null;

        if ($request->filled('resume')) {
            $order = Order::query()
                ->with(['items.modifiers', 'table'])
                ->where('order_number', $request->string('resume'))
                ->whereIn('status', [OrderStatus::Held->value, OrderStatus::Draft->value])
                ->first();

            if ($order) {
                $resume = [
                    'order_number' => $order->order_number,
                    'order_type' => $order->order_type->value,
                    'table_id' => $order->restaurant_table_id,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'customer_address' => $order->customer_address,
                    'delivery_instructions' => $order->delivery_instructions,
                    'notes' => $order->notes,
                    'discount_type' => $order->order_discount_minor > 0 ? 'fixed' : null,
                    'discount_value' => $order->order_discount_minor > 0 ? number_format($order->order_discount_minor / 100, 2, '.', '') : '',
                    'items' => $order->items->map(fn ($item) => [
                        'product_id' => $item->product_id,
                        'variant_id' => $item->product_variant_id,
                        'quantity' => $item->quantity,
                        'notes' => $item->notes,
                        'modifier_ids' => $item->modifiers->pluck('modifier_id')->filter()->values()->all(),
                    ])->all(),
                ];
            }
        }

        $held = Order::query()
            ->whereIn('status', [OrderStatus::Held->value, OrderStatus::Draft->value])
            ->withCount('items')
            ->with('table')
            ->latest('held_at')
            ->limit(6)
            ->get();

        return view('pos.index', [
            'categories' => $categories,
            'products' => $products,
            'tables' => $tables,
            'resume' => $resume,
            'held' => $held,
            'heldCount' => Order::query()->whereIn('status', [OrderStatus::Held->value, OrderStatus::Draft->value])->count(),
            'shift' => $request->user()->openShift(),
            'showKitchen' => $this->settings->bool('orders.show_kitchen_after_checkout', true),
            'preselectedTable' => $request->integer('table') ?: null,
        ]);
    }

    public function checkout(StoreOrderRequest $request): RedirectResponse|JsonResponse
    {
        $order = $this->pos->checkout($request->validated(), $request->user());

        $receiptUrl = route('orders.receipt', [
            'order' => $order->order_number,
            'autoprint' => 1,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'order' => $order->order_number,
                'total' => $this->settings->money($order->total_minor),
                'change' => $order->payments->first()?->change_minor ?? 0,
                'receipt_url' => $receiptUrl,
                'redirect' => $receiptUrl,
            ]);
        }

        return redirect()->to($receiptUrl)->with('success', 'Order '.$order->order_number.' completed.');
    }

    public function hold(HoldOrderRequest $request): RedirectResponse|JsonResponse
    {
        $order = $this->pos->hold($request->validated(), $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'order' => $order->order_number,
                'message' => 'Order held as '.$order->order_number.'.',
                'redirect' => route('pos.index'),
            ]);
        }

        return redirect()->route('pos.index')->with('success', 'Order held as '.$order->order_number.'.');
    }

    public function held(): View
    {
        $orders = Order::query()
            ->whereIn('status', [OrderStatus::Held->value, OrderStatus::Draft->value])
            ->with(['items.modifiers', 'table', 'user'])
            ->latest('held_at')
            ->paginate(20);

        return view('pos.held', compact('orders'));
    }

    public function discard(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->isHeld(), 404);

        $this->pos->cancelHeld($order, $request->user(), 'Discarded from held orders list.');

        return redirect()->route('pos.held')->with('success', 'Held order discarded.');
    }

    public function resume(Order $order): RedirectResponse
    {
        abort_unless($order->isHeld(), 404);

        return redirect()->route('pos.index', ['resume' => $order->order_number, 'table' => $order->restaurant_table_id]);
    }
}
