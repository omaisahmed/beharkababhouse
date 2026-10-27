<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Recipe;
use App\Services\AuditService;
use App\Support\Units;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-inventory');

        $query = Product::query()->with(['category', 'recipe.ingredients.inventoryItem']);

        if ($search = trim((string) $request->input('q'))) {
            $query->where('name', 'like', "%{$search}%");
        }

        $products = $query->ordered()->paginate(25)->withQueryString();

        return view('inventory.recipes.index', compact('products'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('manage-inventory');

        $product->load('recipe.ingredients.inventoryItem');

        return view('inventory.recipes.form', [
            'product' => $product,
            'recipe' => $product->recipe ?? new Recipe(['yield_quantity' => 1, 'is_active' => true]),
            'items' => InventoryItem::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-inventory');

        $data = $request->validate([
            'yield_quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*.inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'ingredients.*.quantity' => ['nullable', 'string', 'max:20'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:10'],
        ]);

        $rows = [];

        foreach ($data['ingredients'] ?? [] as $row) {
            $itemId = (int) ($row['inventory_item_id'] ?? 0);

            if ($itemId === 0) {
                continue;
            }

            $item = InventoryItem::query()->findOrFail($itemId);

            if (isset($rows[$itemId])) {
                throw ValidationException::withMessages(['ingredients' => 'Each ingredient can only appear once in a recipe.']);
            }

            $unit = $row['unit'] ?? $item->unit;

            if (! array_key_exists($unit, Units::inputUnitsFor($item->unit))) {
                throw ValidationException::withMessages(['ingredients' => 'Invalid unit selected for '.$item->name.'.']);
            }

            try {
                $quantity = Units::toMilli($row['quantity'] ?? 0, $unit, $item->unit);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['ingredients' => 'Enter a valid quantity for '.$item->name.'.']);
            }

            if ($quantity <= 0) {
                throw ValidationException::withMessages(['ingredients' => 'Ingredient quantities must be greater than zero.']);
            }

            $rows[$itemId] = $quantity;
        }

        DB::transaction(function () use ($product, $data, $rows) {
            $recipe = Recipe::query()->firstOrNew(['product_id' => $product->id]);
            $recipe->fill([
                'name' => $product->name,
                'yield_quantity' => $data['yield_quantity'],
                'is_active' => request()->boolean('is_active'),
                'notes' => $data['notes'] ?? null,
            ]);
            $recipe->product_id = $product->id;
            $recipe->save();

            $recipe->ingredients()->delete();

            foreach ($rows as $itemId => $quantity) {
                $recipe->ingredients()->create([
                    'inventory_item_id' => $itemId,
                    'quantity_milli' => $quantity,
                ]);
            }
        });

        $audit->log('recipe.updated', 'Updated recipe for '.$product->name.'.', $product, ['ingredients' => count($rows)]);

        return redirect()->route('recipes.index')->with('success', 'Recipe saved.');
    }
}
