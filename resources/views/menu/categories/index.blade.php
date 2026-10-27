@extends('layouts.app')

@section('title', 'Categories')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Menu categories</h1>
            <p class="page-subtitle">Group products into the sections cashiers see on the POS.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('products.index') }}" class="btn btn-ghost">Products</a>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('category-dialog').showModal()"><x-icon name="plus" class="w-4 h-4" /> Add category</button>
        </div>
    </div>

    @if ($categories->isEmpty())
        <div class="panel">
            <div class="empty" style="padding:70px 20px;">
                <div class="empty-icon"><x-icon name="categories" class="w-7 h-7" /></div>
                <div class="empty-title">No categories yet</div>
                <div class="empty-text">Start with sections such as Kebabs, Curries, Breads, and Drinks.</div>
                <button type="button" class="btn btn-primary" style="margin-top:14px;" onclick="document.getElementById('category-dialog').showModal()">Add your first category</button>
            </div>
        </div>
    @else
        <div class="panel">
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th style="width:54px;"></th><th>Category</th><th>Slug</th><th class="r">Products</th><th class="r">Order</th><th>Status</th><th class="r"></th></tr></thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <td>
                                    <div class="thumb">
                                        @if ($category->image_path)
                                            <img src="{{ route('media', ['path' => $category->image_path]) }}" alt="">
                                        @else
                                            <x-icon name="categories" class="w-4 h-4" />
                                        @endif
                                    </div>
                                </td>
                                <td><div style="font-weight:650;">{{ $category->name }}</div>@if ($category->description)<div class="tiny">{{ \Illuminate\Support\Str::limit($category->description, 70) }}</div>@endif</td>
                                <td class="tiny">{{ $category->slug }}</td>
                                <td class="r">{{ $category->products_count }}</td>
                                <td class="r">{{ $category->sort_order }}</td>
                                <td><span class="badge {{ $category->is_active ? 'badge-emerald' : 'badge-neutral' }}">{{ $category->is_active ? 'Active' : 'Hidden' }}</span></td>
                                <td class="r">
                                    <div style="display:flex;gap:6px;justify-content:flex-end;">
                                        <button type="button" class="btn btn-ghost btn-sm" data-edit-category="{{ $category->id }}" data-name="{{ $category->name }}" data-description="{{ $category->description }}" data-sort="{{ $category->sort_order }}" data-active="{{ $category->is_active ? 1 : 0 }}" data-image="{{ $category->image_path ? route('media', ['path' => $category->image_path]) : '' }}">Edit</button>
                                        <form method="POST" action="{{ route('categories.destroy', $category) }}" data-confirm="Remove {{ $category->name }}?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);" @disabled($category->products_count > 0)>Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@push('modals')
    <dialog class="modal" id="category-dialog">
        <form method="POST" action="{{ route('categories.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-head"><div class="modal-title">Add category</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
            <div class="modal-body">
                <div class="field"><label class="label">Name</label><input name="name" class="input" required maxlength="80" placeholder="Kebabs"></div>
                <div class="field" style="margin-top:12px;"><label class="label">Description</label><textarea name="description" class="textarea" rows="2" maxlength="500"></textarea></div>
                <div class="form-grid" style="margin-top:12px;">
                    <div class="field"><label class="label">Sort order</label><input name="sort_order" type="number" min="0" max="9999" value="0" class="input"></div>
                    <div class="field"><label class="label">Image</label><input name="image" type="file" accept="image/*" class="input"></div>
                </div>
                <label class="check" style="margin-top:12px;"><input type="checkbox" name="is_active" value="1" checked><span>Visible on POS</span></label>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Create category</button></div>
        </form>
    </dialog>

    <dialog class="modal" id="category-edit-dialog">
        <form method="POST" id="category-edit-form" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="modal-head"><div class="modal-title">Edit category</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
            <div class="modal-body">
                <div class="field"><label class="label">Name</label><input name="name" id="category-name" class="input" required maxlength="80"></div>
                <div class="field" style="margin-top:12px;"><label class="label">Description</label><textarea name="description" id="category-description" class="textarea" rows="2" maxlength="500"></textarea></div>
                <div class="form-grid" style="margin-top:12px;">
                    <div class="field"><label class="label">Sort order</label><input name="sort_order" id="category-sort" type="number" min="0" max="9999" class="input"></div>
                    <div class="field"><label class="label">Replace image</label><input name="image" type="file" accept="image/*" class="input"></div>
                </div>
                <div id="category-image-wrap" style="margin-top:12px;display:none;">
                    <img id="category-image-preview" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:10px;border:1px solid var(--border);">
                    <label class="check" style="margin-top:8px;"><input type="checkbox" name="remove_image" value="1"><span>Remove current image</span></label>
                </div>
                <label class="check" style="margin-top:12px;"><input type="checkbox" name="is_active" value="1" id="category-active"><span>Visible on POS</span></label>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
        </form>
    </dialog>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-edit-category]').forEach((button) => {
            button.addEventListener('click', () => {
                const form = document.getElementById('category-edit-form');
                form.action = @json(url('/categories')) + '/' + button.dataset.editCategory;
                document.getElementById('category-name').value = button.dataset.name || '';
                document.getElementById('category-description').value = button.dataset.description || '';
                document.getElementById('category-sort').value = button.dataset.sort || 0;
                document.getElementById('category-active').checked = button.dataset.active === '1';
                const wrap = document.getElementById('category-image-wrap');
                const preview = document.getElementById('category-image-preview');
                if (button.dataset.image) {
                    preview.src = button.dataset.image;
                    wrap.style.display = 'block';
                } else {
                    wrap.style.display = 'none';
                }
                document.getElementById('category-edit-dialog').showModal();
            });
        });
    });
</script>
@endpush
