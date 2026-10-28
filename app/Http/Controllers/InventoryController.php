<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Services\AuditService;
use App\Services\InventoryService;
use App\Support\Money;
use App\Support\Quantity;
use App\Support\Units;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-inventory');

        $query = InventoryItem::query()->with('supplier');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('low')) {
            $query->where('minimum_stock_milli', '>', 0)->whereColumn('stock_milli', '<=', 'minimum_stock_milli');
        }

        $items = $query->orderBy('name')->paginate(30)->withQueryString();
        $all = InventoryItem::query()->get();
        $summary = [
            'items' => $all->count(),
            'low' => $all->filter->isLowStock()->count(),
            'out' => $all->filter(fn (InventoryItem $item) => $item->stock_milli <= 0)->count(),
            'value' => (int) $all->sum(fn (InventoryItem $item) => $item->stockValueMinor()),
        ];

        $suppliers = Supplier::query()->active()->orderBy('name')->get();

        return view('inventory.index', compact('items', 'summary', 'suppliers'));
    }

    public function store(Request $request, InventoryService $inventory, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-inventory');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:60', 'unique:inventory_items,sku'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'unit' => ['required', Rule::in(Units::CANONICAL)],
            'minimum_stock' => ['nullable', 'string', 'max:20'],
            'cost_per_unit' => ['nullable', 'string', 'max:20'],
            'opening_stock' => ['nullable', 'string', 'max:20'],
            'opening_unit' => ['nullable', Rule::in(Units::ALL)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $cost = Money::parse($data['cost_per_unit'] ?? 0);
            $minimum = $data['unit'] === 'piece'
                ? Quantity::parse($data['minimum_stock'] ?? 0)
                : Units::toMilli($data['minimum_stock'] ?? 0, $data['unit'], $data['unit']);
            $opening = Units::toMilli($data['opening_stock'] ?? 0, $data['opening_unit'] ?? $data['unit'], $data['unit']);
        } catch (\InvalidArgumentException) {
            return back()->withErrors(['unit' => 'Enter valid quantities and costs.'])->withInput();
        }

        DB::transaction(function () use ($data, $minimum, $cost, $opening, $inventory, $audit, $request) {
            $item = InventoryItem::query()->create([
                'name' => $data['name'],
                'sku' => ($data['sku'] ?? '') !== '' ? $data['sku'] : null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'unit' => $data['unit'],
                'stock_milli' => 0,
                'minimum_stock_milli' => max(0, $minimum),
                'cost_per_unit_minor' => max(0, $cost),
                'is_active' => true,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($opening !== 0) {
                $inventory->adjust($item, $opening, StockMovementType::Opening, 'Opening stock entered when the item was created.', $request->user(), max(0, $cost));
            }

            $audit->log('inventory.created', 'Created inventory item '.$item->name.'.', $item, ['unit' => $item->unit]);
        });

        return back()->with('success', 'Inventory item created.');
    }

    public function update(Request $request, InventoryItem $item, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-inventory');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('inventory_items', 'sku')->ignore($item->id)],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'minimum_stock' => ['nullable', 'string', 'max:20'],
            'cost_per_unit' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $minimum = $item->unit === 'piece'
                ? Quantity::parse($data['minimum_stock'] ?? 0)
                : Units::toMilli($data['minimum_stock'] ?? 0, $item->unit, $item->unit);
            $cost = Money::parse($data['cost_per_unit'] ?? 0);
        } catch (\InvalidArgumentException) {
            return back()->withErrors(['cost_per_unit' => 'Enter valid quantities and costs.'])->withInput();
        }

        $item->update([
            'name' => $data['name'],
            'sku' => ($data['sku'] ?? '') !== '' ? $data['sku'] : null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'minimum_stock_milli' => max(0, $minimum),
            'cost_per_unit_minor' => max(0, $cost),
            'is_active' => $request->boolean('is_active'),
            'notes' => $data['notes'] ?? null,
        ]);

        $audit->log('inventory.updated', 'Updated inventory item '.$item->name.'.', $item);

        return back()->with('success', 'Inventory item updated.');
    }

    public function adjust(Request $request, InventoryItem $item, InventoryService $inventory): RedirectResponse
    {
        $this->authorize('manage-inventory');

        $data = $request->validate([
            'type' => ['required', Rule::in(['wastage', 'damage', 'adjustment', 'opening'])],
            'quantity' => ['required', 'string', 'max:20'],
            'unit' => ['required', Rule::in(array_keys(Units::inputUnitsFor($item->unit)))],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        try {
            $quantity = Units::toMilli($data['quantity'], $data['unit'], $item->unit);
        } catch (\InvalidArgumentException) {
            return back()->withErrors(['quantity' => 'Enter a valid quantity for this unit.'])->withInput();
        }

        $inventory->adjust($item, $quantity, StockMovementType::from($data['type']), $data['reason'], $request->user());

        return back()->with('success', 'Stock adjusted.');
    }

    public function restock(Request $request, InventoryItem $item, InventoryService $inventory): RedirectResponse
    {
        $this->authorize('manage-inventory');

        $data = $request->validate([
            'quantity' => ['required', 'string', 'max:20'],
            'unit' => ['required', Rule::in(array_keys(Units::inputUnitsFor($item->unit)))],
            'unit_cost' => ['required', 'string', 'max:20'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $quantity = Units::toMilli($data['quantity'], $data['unit'], $item->unit);
            $cost = Money::parse($data['unit_cost']);
        } catch (\InvalidArgumentException) {
            return back()->withErrors(['quantity' => 'Enter a valid quantity and cost.'])->withInput();
        }

        DB::transaction(function () use ($inventory, $item, $quantity, $data, $cost, $request) {
            $inventory->adjust($item, abs($quantity), StockMovementType::Purchase, $data['reason'] ?? 'Quick restock entered from the stock screen.', $request->user(), $cost);

            $item->forceFill(['cost_per_unit_minor' => max(0, $cost)])->save();
        });

        return back()->with('success', 'Stock received.');
    }

    public function destroy(InventoryItem $item, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-inventory');

        if ($item->recipeIngredients()->exists()) {
            return back()->with('error', 'This item is used in a recipe and cannot be removed.');
        }

        $audit->log('inventory.deleted', 'Deleted inventory item '.$item->name.'.', null, ['name' => $item->name]);
        $item->delete();

        return back()->with('success', 'Inventory item removed.');
    }
}
