<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AuditService;
use App\Services\MediaService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-menu');

        $query = Product::query()->with('category')->withCount('variants');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($category = $request->integer('category')) {
            $query->where('category_id', $category);
        }

        if ($request->input('availability') === 'available') {
            $query->where('is_available', true);
        } elseif ($request->input('availability') === 'unavailable') {
            $query->where('is_available', false);
        }

        $products = $query->ordered()->paginate(20)->withQueryString();
        $categories = Category::query()->ordered()->get();
        $stats = [
            'total' => Product::query()->count(),
            'available' => Product::query()->where('is_available', true)->count(),
            'unavailable' => Product::query()->where('is_available', false)->count(),
            'categories' => $categories->count(),
        ];

        return view('menu.products.index', compact('products', 'categories', 'stats'));
    }

    public function create(): View
    {
        $this->authorize('manage-menu');

        return view('menu.products.form', [
            'product' => new Product(['is_available' => true, 'is_taxable' => true]),
            'categories' => Category::query()->ordered()->get(),
            'modifierGroups' => ModifierGroup::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedGroups' => [],
            'variants' => collect(),
        ]);
    }

    public function store(Request $request, MediaService $media, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-menu');

        $data = $this->validated($request, $media);

        $product = DB::transaction(function () use ($data) {
            $variants = $data['variants'];
            $groups = $data['modifier_groups'] ?? [];
            unset($data['variants'], $data['modifier_groups'], $data['image'], $data['remove_image']);

            $product = Product::query()->create($data);
            $this->syncVariants($product, $variants);
            $product->modifierGroups()->sync($groups);

            return $product;
        });

        $audit->log('product.created', 'Created product '.$product->name.'.', $product, ['price_minor' => $product->price_minor]);

        return redirect()->route('products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product): View
    {
        $this->authorize('manage-menu');

        $product->load(['variants', 'modifierGroups']);

        return view('menu.products.form', [
            'product' => $product,
            'categories' => Category::query()->ordered()->get(),
            'modifierGroups' => ModifierGroup::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedGroups' => $product->modifierGroups->pluck('id')->all(),
            'variants' => $product->variants,
        ]);
    }

    public function update(Request $request, Product $product, MediaService $media, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-menu');

        $data = $this->validated($request, $media, $product);

        DB::transaction(function () use ($product, $data) {
            $variants = $data['variants'];
            $groups = $data['modifier_groups'] ?? [];
            unset($data['variants'], $data['modifier_groups'], $data['image'], $data['remove_image']);

            $product->update($data);
            $this->syncVariants($product, $variants);
            $product->modifierGroups()->sync($groups);
        });

        $audit->log('product.updated', 'Updated product '.$product->name.'.', $product, ['price_minor' => $product->price_minor]);

        return redirect()->route('products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product, MediaService $media, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-menu');

        $media->delete($product->image_path);
        $audit->log('product.deleted', 'Deleted product '.$product->name.'.', null, ['name' => $product->name]);
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product removed.');
    }

    public function toggle(Product $product, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-menu');

        $product->forceFill(['is_available' => ! $product->is_available])->save();

        $audit->log(
            'product.availability',
            ($product->is_available ? 'Marked available: ' : 'Marked unavailable: ').$product->name,
            $product,
            ['is_available' => $product->is_available],
        );

        return back()->with('success', $product->name.' is now '.($product->is_available ? 'available' : 'unavailable').'.');
    }

    public function export(): StreamedResponse
    {
        $this->authorize('manage-menu');

        $filename = 'menu-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['category', 'name', 'sku', 'description', 'price', 'cost', 'tax_rate', 'is_taxable', 'is_available', 'kitchen_station', 'preparation_minutes', 'sort_order']);

            Product::query()->with('category')->ordered()->chunk(200, function ($products) use ($handle) {
                foreach ($products as $product) {
                    fputcsv($handle, [
                        $product->category?->name,
                        $product->name,
                        $product->sku,
                        $product->description,
                        Money::toDecimal($product->price_minor),
                        $product->cost_minor !== null ? Money::toDecimal($product->cost_minor) : '',
                        $product->tax_rate_bp !== null ? number_format($product->tax_rate_bp / 100, 2, '.', '') : '',
                        $product->is_taxable ? '1' : '0',
                        $product->is_available ? '1' : '0',
                        $product->kitchen_station,
                        $product->preparation_minutes,
                        $product->sort_order,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-menu');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        if (! $handle) {
            return back()->with('error', 'The CSV file could not be read.');
        }

        $headers = fgetcsv($handle);

        if (! $headers) {
            fclose($handle);

            return back()->with('error', 'The CSV file is empty.');
        }

        $headers = array_map(fn ($header) => Str::snake(trim((string) $header)), $headers);
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($handle, $headers, &$created, &$updated, &$skipped) {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== count($headers)) {
                    $skipped++;

                    continue;
                }

                $record = array_combine($headers, array_map('trim', $row));
                $name = $record['name'] ?? '';
                $categoryName = $record['category'] ?? '';

                if ($name === '' || $categoryName === '') {
                    $skipped++;

                    continue;
                }

                try {
                    $category = Category::query()->firstOrCreate(
                        ['slug' => Str::slug($categoryName) ?: 'imported'],
                        ['name' => $categoryName, 'is_active' => true, 'sort_order' => 999],
                    );

                    $sku = trim((string) ($record['sku'] ?? ''));
                    $product = $sku !== '' ? (Product::query()->firstWhere('sku', $sku) ?? new Product) : new Product;
                    $product->fill([
                        'category_id' => $category->id,
                        'name' => $name,
                        'sku' => ($record['sku'] ?? '') !== '' ? $record['sku'] : null,
                        'description' => $record['description'] ?? null,
                        'price_minor' => Money::parse($record['price'] ?? 0),
                        'cost_minor' => ($record['cost'] ?? '') !== '' ? Money::parse($record['cost']) : null,
                        'tax_rate_bp' => ($record['tax_rate'] ?? '') !== '' ? Money::parse($record['tax_rate']) : null,
                        'is_taxable' => ($record['is_taxable'] ?? '1') !== '0',
                        'is_available' => ($record['is_available'] ?? '1') !== '0',
                        'kitchen_station' => $record['kitchen_station'] ?? null,
                        'preparation_minutes' => ($record['preparation_minutes'] ?? '') !== '' ? (int) $record['preparation_minutes'] : null,
                        'sort_order' => (int) ($record['sort_order'] ?? 0),
                    ]);

                    $product->exists ? $updated++ : $created++;
                    $product->save();
                } catch (\Throwable) {
                    $skipped++;
                }
            }
        });

        fclose($handle);

        $audit->log('products.imported', "Imported menu CSV: {$created} created, {$updated} updated, {$skipped} skipped.", null, compact('created', 'updated', 'skipped'));

        return redirect()->route('products.index')->with('success', "Import finished: {$created} created, {$updated} updated, {$skipped} skipped.");
    }

    protected function validated(Request $request, MediaService $media, ?Product $product = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('products', 'sku')->ignore($product?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'string', 'max:20'],
            'cost' => ['nullable', 'string', 'max:20'],
            'tax_rate' => ['nullable', 'string', 'max:10'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_available' => ['nullable', 'boolean'],
            'kitchen_station' => ['nullable', 'string', 'max:60'],
            'preparation_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'modifier_groups' => ['nullable', 'array'],
            'modifier_groups.*' => ['integer', 'exists:modifier_groups,id'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['nullable', 'string', 'max:60'],
            'variants.*.price_delta' => ['nullable', 'string', 'max:20'],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'variants.*.is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $data['price_minor'] = Money::parse($data['price']);
            $data['cost_minor'] = ($data['cost'] ?? '') !== '' ? Money::parse($data['cost']) : null;
            $data['tax_rate_bp'] = ($data['tax_rate'] ?? '') !== '' ? max(0, min(10000, Money::parse($data['tax_rate']))) : null;
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['price' => 'Enter valid money and percentage values.']);
        }

        $data['sku'] = ($data['sku'] ?? '') !== '' ? $data['sku'] : null;
        $data['is_taxable'] = $request->boolean('is_taxable');
        $data['is_available'] = $request->boolean('is_available');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        if ($request->hasFile('image')) {
            if ($product) {
                $media->delete($product->image_path);
            }
            $data['image_path'] = $media->store($request->file('image'), 'products');
        } elseif ($product && $request->boolean('remove_image')) {
            $media->delete($product->image_path);
            $data['image_path'] = null;
        }

        $variants = [];

        foreach ($data['variants'] ?? [] as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            try {
                $delta = Money::parse($row['price_delta'] ?? 0);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['variants' => 'Enter valid variant price adjustments.']);
            }

            $variants[] = [
                'id' => $row['id'] ?? null,
                'name' => $name,
                'price_delta_minor' => $delta,
                'is_default' => filter_var($row['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        if (count(collect($variants)->pluck('name')->unique()) !== count($variants)) {
            throw ValidationException::withMessages(['variants' => 'Variant names must be unique for this product.']);
        }

        if ($variants && ! collect($variants)->contains('is_default', true)) {
            $variants[0]['is_default'] = true;
        }

        $defaultSeen = false;

        foreach ($variants as &$variant) {
            if ($variant['is_default']) {
                if ($defaultSeen) {
                    $variant['is_default'] = false;
                }
                $defaultSeen = true;
            }
        }

        unset($variant);

        $data['variants'] = $variants;

        unset($data['price'], $data['cost'], $data['tax_rate'], $data['image']);

        return $data;
    }

    protected function syncVariants(Product $product, array $variants): void
    {
        $existing = $product->variants()->get()->keyBy('id');
        $kept = [];

        foreach ($variants as $index => $row) {
            $variant = $row['id'] ? $existing->get((int) $row['id']) : null;
            $variant ??= new ProductVariant(['product_id' => $product->id]);

            $variant->fill([
                'name' => $row['name'],
                'price_delta_minor' => $row['price_delta_minor'],
                'is_default' => $row['is_default'],
                'is_active' => $row['is_active'],
                'sort_order' => $index,
            ]);
            $variant->product_id = $product->id;
            $variant->save();
            $kept[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $kept)->delete();
    }
}
