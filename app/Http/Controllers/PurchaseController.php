<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\AuditService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-inventory');

        $query = Purchase::query()->with(['supplier', 'user'])->withCount('items');

        if ($supplier = $request->integer('supplier')) {
            $query->where('supplier_id', $supplier);
        }

        if ($status = $request->input('status')) {
            $query->where('payment_status', $status);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('purchased_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('purchased_at', '<=', $to);
        }

        $purchases = $query->latest('purchased_at')->latest('id')->paginate(20)->withQueryString();
        $suppliers = Supplier::query()->orderBy('name')->get();
        $summary = [
            'total' => (int) Purchase::query()->sum('total_minor'),
            'unpaid' => (int) Purchase::query()->where('payment_status', '!=', 'paid')->sum(DB::raw('total_minor - paid_minor')),
        ];

        return view('inventory.purchases.index', compact('purchases', 'suppliers', 'summary'));
    }

    public function create(): View
    {
        $this->authorize('manage-inventory');

        return view('inventory.purchases.form', [
            'suppliers' => Supplier::query()->active()->orderBy('name')->get(),
            'items' => InventoryItem::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        $this->authorize('manage-inventory');

        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'purchased_at' => ['required', 'date', 'before_or_equal:today'],
            'payment_status' => ['required', Rule::in(['unpaid', 'partial', 'paid'])],
            'paid' => ['nullable', 'string', 'max:20'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'items.*.quantity' => ['required', 'string', 'max:20'],
            'items.*.unit' => ['required', 'string', 'max:10'],
            'items.*.unit_cost' => ['required', 'string', 'max:20'],
        ]);

        try {
            $purchase = $inventory->purchase($data, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        } catch (\InvalidArgumentException) {
            return back()->withErrors(['items' => 'Enter valid quantities and costs.'])->withInput();
        }

        return redirect()->route('purchases.show', $purchase)->with('success', 'Purchase recorded and stock updated.');
    }

    public function show(Purchase $purchase): View
    {
        $this->authorize('manage-inventory');

        $purchase->load(['supplier', 'user', 'items.inventoryItem']);

        return view('inventory.purchases.show', compact('purchase'));
    }

    public function destroy(Request $request, Purchase $purchase, InventoryService $inventory, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-inventory');

        if (! $request->user()->isAdmin()) {
            return back()->with('error', 'Only an administrator can reverse a purchase.');
        }

        DB::transaction(function () use ($purchase, $inventory, $request, $audit) {
            $purchase->load('items');

            foreach ($purchase->items as $line) {
                $inventory->adjust(
                    $line->inventoryItem,
                    -abs($line->quantity_milli),
                    StockMovementType::Reversal,
                    'Purchase '.$purchase->purchase_number.' reversed.',
                    $request->user(),
                    $line->unit_cost_minor,
                );
            }

            $audit->log('purchase.reversed', 'Reversed purchase '.$purchase->purchase_number.'.', null, ['total_minor' => $purchase->total_minor]);
            $purchase->delete();
        });

        return redirect()->route('purchases.index')->with('success', 'Purchase reversed and stock adjusted.');
    }
}
