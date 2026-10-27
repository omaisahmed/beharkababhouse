@extends('layouts.app')

@section('title', 'Recipes')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Recipes</h1>
            <p class="page-subtitle">Link products to ingredients so stock is deducted automatically at checkout.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('inventory.index') }}" class="btn btn-ghost">Stock levels</a>
        </div>
    </div>

    <div class="panel">
        <form method="GET" action="{{ route('recipes.index') }}" class="panel-body" style="display:flex;gap:9px;align-items:center;border-bottom:1px solid var(--border);">
            <input name="q" value="{{ request('q') }}" class="input" placeholder="Search products…" style="max-width:280px;">
            <button type="submit" class="btn btn-ghost">Search</button>
            @if (request('q'))<a href="{{ route('recipes.index') }}" class="btn btn-ghost">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Product</th><th>Category</th><th class="r">Ingredients</th><th>Recipe</th><th>Status</th><th class="r"></th></tr></thead>
                <tbody>
                    @forelse ($products as $product)
                        @php $recipe = $product->recipe; @endphp
                        <tr>
                            <td><div style="font-weight:650;">{{ $product->name }}</div><div class="tiny">{{ $product->sku ?: 'No SKU' }}</div></td>
                            <td class="tiny">{{ $product->category?->name ?? '—' }}</td>
                            <td class="r">{{ $recipe?->ingredients->count() ?? 0 }}</td>
                            <td class="tiny">
                                @if ($recipe)
                                    {{ $recipe->ingredients->take(3)->map(fn ($ingredient) => $ingredient->inventoryItem?->name)->filter()->implode(', ') }}{{ $recipe->ingredients->count() > 3 ? '…' : '' }}
                                @else
                                    Not configured
                                @endif
                            </td>
                            <td>
                                @if (! $recipe)
                                    <span class="badge badge-neutral">No recipe</span>
                                @elseif ($recipe->is_active)
                                    <span class="badge badge-emerald">Active</span>
                                @else
                                    <span class="badge badge-amber">Inactive</span>
                                @endif
                            </td>
                            <td class="r"><a href="{{ route('recipes.edit', $product) }}" class="btn btn-ghost btn-sm">Configure</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty" style="padding:50px 20px;"><div class="empty-icon"><x-icon name="recipe" class="w-7 h-7" /></div><div class="empty-title">No products found</div><div class="empty-text">Create menu products first, then attach recipes.</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())<div class="panel-body">{{ $products->links() }}</div>@endif
    </div>
@endsection
