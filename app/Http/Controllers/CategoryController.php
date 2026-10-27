<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\AuditService;
use App\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('manage-menu');

        $categories = Category::query()
            ->withCount('products')
            ->ordered()
            ->get();

        return view('menu.categories.index', compact('categories'));
    }

    public function store(Request $request, MediaService $media, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-menu');

        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $media->store($request->file('image'), 'categories');
        }

        $data['slug'] = $this->uniqueSlug($data['name']);
        $category = Category::query()->create($data);

        $audit->log('category.created', 'Created category '.$category->name.'.', $category, ['name' => $category->name]);

        return redirect()->route('categories.index')->with('success', 'Category created.');
    }

    public function update(Request $request, Category $category, MediaService $media, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-menu');

        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $media->delete($category->image_path);
            $data['image_path'] = $media->store($request->file('image'), 'categories');
        } elseif ($request->boolean('remove_image')) {
            $media->delete($category->image_path);
            $data['image_path'] = null;
        }

        if ($category->name !== $data['name']) {
            $data['slug'] = $this->uniqueSlug($data['name'], $category->id);
        }

        $category->update($data);

        $audit->log('category.updated', 'Updated category '.$category->name.'.', $category, ['name' => $category->name]);

        return redirect()->route('categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-menu');

        if ($category->products()->exists()) {
            return back()->with('error', 'Move or delete the products in this category first.');
        }

        $audit->log('category.deleted', 'Deleted category '.$category->name.'.', null, ['name' => $category->name]);
        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Category removed.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $counter = 2;

        while (Category::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
