<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Support\Units;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-inventory');

        $query = StockMovement::query()->with(['inventoryItem', 'user'])->latest('id');

        if ($item = $request->integer('item')) {
            $query->where('inventory_item_id', $item);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $movements = $query->paginate(30)->withQueryString();
        $items = InventoryItem::query()->orderBy('name')->get();

        return view('inventory.movements', compact('movements', 'items'));
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        $this->authorize('manage-inventory');

        $data = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'type' => ['required', Rule::enum(StockMovementType::class)],
            'quantity' => ['required', 'string', 'max:20'],
            'unit' => ['required', 'string', 'max:10'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $item = InventoryItem::query()->findOrFail($data['inventory_item_id']);

        if (! array_key_exists($data['unit'], Units::inputUnitsFor($item->unit))) {
            return back()->withErrors(['unit' => 'That unit cannot be used for '.$item->name.'.'])->withInput();
        }

        try {
            $quantity = Units::toMilli($data['quantity'], $data['unit'], $item->unit);
        } catch (\InvalidArgumentException) {
            return back()->withErrors(['quantity' => 'Enter a valid quantity.'])->withInput();
        }

        $type = StockMovementType::from($data['type']);

        if ($type === StockMovementType::Purchase) {
            return back()->withErrors(['type' => 'Use the Purchases screen to receive purchased stock.'])->withInput();
        }

        $inventory->adjust($item, $quantity, $type, $data['reason'], $request->user());

        return back()->with('success', 'Stock movement recorded.');
    }
}
