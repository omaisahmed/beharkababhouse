@extends('layouts.app')

@section('title', 'Expenses')

@php $money = app(\App\Services\SettingsService::class); @endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Expenses</h1>
            <p class="page-subtitle">Rent, utilities, wages, and other operating costs.</p>
        </div>
        <div class="page-actions">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('categories-dialog').showModal()">Categories</button>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('expense-dialog').showModal()"><x-icon name="plus" class="w-4 h-4" /> Record expense</button>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">This month</div><div class="stat-value">{{ $money->money($summary['month']) }}</div><div class="stat-meta">{{ now()->format('F Y') }}</div></div>
        <div class="stat"><div class="stat-label">All time</div><div class="stat-value">{{ $money->money($summary['total']) }}</div><div class="stat-meta">{{ $summary['count'] }} expenses</div></div>
    </div>

    <div class="panel" style="margin-top:18px;">
        <form method="GET" action="{{ route('expenses.index') }}" class="panel-body" style="display:flex;gap:9px;flex-wrap:wrap;align-items:flex-end;border-bottom:1px solid var(--border);">
            <div class="field" style="min-width:220px;"><label class="label">Search</label><input name="q" value="{{ request('q') }}" class="input" placeholder="Description or reference…"></div>
            <div class="field" style="min-width:180px;"><label class="label">Category</label><select name="category" class="select"><option value="">All categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
            <div class="field"><label class="label">From</label><input type="date" name="from" value="{{ request('from') }}" class="input"></div>
            <div class="field"><label class="label">To</label><input type="date" name="to" value="{{ request('to') }}" class="input"></div>
            <button type="submit" class="btn btn-ghost">Filter</button>
            @if (request()->hasAny(['q', 'category', 'from', 'to']))<a href="{{ route('expenses.index') }}" class="btn btn-ghost">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Method</th><th class="r">Amount</th><th>Recorded by</th><th class="r"></th></tr></thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td class="tiny">{{ $expense->expense_date?->format('d M Y') }}</td>
                            <td><div style="font-weight:600;">{{ $expense->description }}</div><div class="tiny">{{ $expense->reference ?: ($expense->supplier?->name ?? '—') }}</div></td>
                            <td class="tiny">{{ $expense->category?->name ?? 'Uncategorised' }}</td>
                            <td class="tiny">{{ $expense->payment_method?->label() }}</td>
                            <td class="r" style="font-weight:650;">{{ $money->money($expense->amount_minor) }}</td>
                            <td class="tiny">{{ $expense->user?->name }}</td>
                            <td class="r">
                                <div style="display:flex;gap:5px;justify-content:flex-end;">
                                    @if ($expense->attachment_path)
                                        <a href="{{ route('media', ['path' => $expense->attachment_path]) }}" target="_blank" class="btn btn-ghost btn-sm">Receipt</a>
                                    @endif
                                    <button type="button" class="btn btn-ghost btn-sm" data-edit-expense="{{ $expense->id }}" data-category="{{ $expense->expense_category_id }}" data-supplier="{{ $expense->supplier_id }}" data-description="{{ $expense->description }}" data-amount="{{ \App\Support\Money::toDecimal($expense->amount_minor) }}" data-date="{{ $expense->expense_date?->toDateString() }}" data-method="{{ $expense->payment_method?->value }}" data-reference="{{ $expense->reference }}" data-notes="{{ $expense->notes }}">Edit</button>
                                    <form method="POST" action="{{ route('expenses.destroy', $expense) }}" data-confirm="Delete this expense?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty" style="padding:50px 20px;"><div class="empty-icon"><x-icon name="expenses" class="w-7 h-7" /></div><div class="empty-title">No expenses</div><div class="empty-text">Record operating costs to see true profit in reports.</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($expenses->hasPages())<div class="panel-body">{{ $expenses->links() }}</div>@endif
    </div>
@endsection

@push('modals')
    <dialog class="modal" id="expense-dialog">
        <form method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-head"><div class="modal-title">Record expense</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
            <div class="modal-body">
                <div class="field"><label class="label">Description</label><input name="description" class="input" required maxlength="180"></div>
                <div class="form-grid" style="margin-top:12px;">
                    <div class="field"><label class="label">Amount</label><input name="amount" class="input" inputmode="decimal" required placeholder="0.00"></div>
                    <div class="field"><label class="label">Date</label><input type="date" name="expense_date" class="input" required value="{{ today()->toDateString() }}"></div>
                    <div class="field"><label class="label">Category</label><select name="expense_category_id" class="select"><option value="">Uncategorised</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
                    <div class="field"><label class="label">Supplier</label><select name="supplier_id" class="select"><option value="">None</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></div>
                    <div class="field"><label class="label">Payment method</label><select name="payment_method" class="select">@foreach ($methods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="field"><label class="label">Reference</label><input name="reference" class="input" maxlength="120"></div>
                </div>
                <div class="field" style="margin-top:12px;"><label class="label">Notes</label><textarea name="notes" class="textarea" rows="2" maxlength="1000"></textarea></div>
                <div class="field" style="margin-top:12px;"><label class="label">Attachment</label><input type="file" name="attachment" class="input" accept="image/*,application/pdf"></div>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Record expense</button></div>
        </form>
    </dialog>

    <dialog class="modal" id="expense-edit-dialog">
        <form method="POST" id="expense-edit-form" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="modal-head"><div class="modal-title">Edit expense</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
            <div class="modal-body">
                <div class="field"><label class="label">Description</label><input name="description" id="expense-description" class="input" required maxlength="180"></div>
                <div class="form-grid" style="margin-top:12px;">
                    <div class="field"><label class="label">Amount</label><input name="amount" id="expense-amount" class="input" inputmode="decimal" required></div>
                    <div class="field"><label class="label">Date</label><input type="date" name="expense_date" id="expense-date" class="input" required></div>
                    <div class="field"><label class="label">Category</label><select name="expense_category_id" id="expense-category" class="select"><option value="">Uncategorised</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
                    <div class="field"><label class="label">Supplier</label><select name="supplier_id" id="expense-supplier" class="select"><option value="">None</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></div>
                    <div class="field"><label class="label">Payment method</label><select name="payment_method" id="expense-method" class="select">@foreach ($methods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="field"><label class="label">Reference</label><input name="reference" id="expense-reference" class="input" maxlength="120"></div>
                </div>
                <div class="field" style="margin-top:12px;"><label class="label">Notes</label><textarea name="notes" id="expense-notes" class="textarea" rows="2" maxlength="1000"></textarea></div>
                <div class="field" style="margin-top:12px;"><label class="label">Replace attachment</label><input type="file" name="attachment" class="input" accept="image/*,application/pdf"></div>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
        </form>
    </dialog>

    <dialog class="modal" id="categories-dialog">
        <div class="modal-head"><div class="modal-title">Expense categories</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
        <div class="modal-body">
            <form method="POST" action="{{ route('expense-categories.store') }}" style="display:flex;gap:8px;align-items:flex-end;">
                @csrf
                <div class="field" style="flex:1;"><label class="label">New category</label><input name="name" class="input" required maxlength="80" placeholder="Utilities"></div>
                <button type="submit" class="btn btn-primary btn-sm">Add</button>
            </form>
            <div style="margin-top:14px;max-height:360px;overflow:auto;">
                @forelse ($categories as $category)
                    <details style="border-bottom:1px solid var(--border);padding:7px 0;">
                        <summary style="cursor:pointer;font-size:12.5px;font-weight:600;list-style:none;display:flex;justify-content:space-between;gap:8px;">
                            <span>{{ $category->name }} @if (! $category->is_active)<span class="tiny">(inactive)</span>@endif</span>
                            <span class="tiny">{{ $category->expenses()->count() }} used</span>
                        </summary>
                        <form method="POST" action="{{ route('expense-categories.update', $category) }}" style="display:flex;gap:7px;align-items:flex-end;margin-top:8px;">
                            @csrf
                            @method('PUT')
                            <div class="field" style="flex:1;"><input name="name" class="input" value="{{ $category->name }}" required maxlength="80"></div>
                            <label class="check"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)><span>Active</span></label>
                            <button type="submit" class="btn btn-ghost btn-sm">Save</button>
                        </form>
                        <form method="POST" action="{{ route('expense-categories.destroy', $category) }}" data-confirm="Delete category {{ $category->name }}?" style="margin-top:6px;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);" @disabled($category->expenses()->exists())>Delete</button>
                        </form>
                    </details>
                @empty
                    <div class="tiny">No categories yet.</div>
                @endforelse
            </div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Close</button></div>
    </dialog>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-edit-expense]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('expense-edit-form').action = @json(url('/expenses')) + '/' + button.dataset.editExpense;
                document.getElementById('expense-description').value = button.dataset.description || '';
                document.getElementById('expense-amount').value = button.dataset.amount || '';
                document.getElementById('expense-date').value = button.dataset.date || '';
                document.getElementById('expense-category').value = button.dataset.category || '';
                document.getElementById('expense-supplier').value = button.dataset.supplier || '';
                document.getElementById('expense-method').value = button.dataset.method || '';
                document.getElementById('expense-reference').value = button.dataset.reference || '';
                document.getElementById('expense-notes').value = button.dataset.notes || '';
                document.getElementById('expense-edit-dialog').showModal();
            });
        });
    });
</script>
@endpush
