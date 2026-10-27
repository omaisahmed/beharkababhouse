<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Services\AuditService;
use App\Services\MediaService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-inventory');

        $query = Expense::query()->with(['category', 'supplier', 'user']);

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('description', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        if ($category = $request->integer('category')) {
            $query->where('expense_category_id', $category);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('expense_date', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('expense_date', '<=', $to);
        }

        $expenses = $query->latest('expense_date')->latest('id')->paginate(25)->withQueryString();
        $categories = ExpenseCategory::query()->orderBy('name')->get();
        $monthTotal = (int) Expense::query()->whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount_minor');
        $summary = [
            'count' => Expense::query()->count(),
            'month' => $monthTotal,
            'total' => (int) Expense::query()->sum('amount_minor'),
        ];

        return view('inventory.expenses.index', [
            'expenses' => $expenses,
            'categories' => $categories,
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'methods' => PaymentMethod::labels(),
            'summary' => $summary,
        ]);
    }

    public function store(Request $request, MediaService $media, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-inventory');

        $data = $this->validated($request);

        try {
            $data['amount_minor'] = Money::parse($data['amount']);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid expense amount.']);
        }

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $media->store($request->file('attachment'), 'expenses');
        }

        $data['user_id'] = $request->user()->id;
        unset($data['amount'], $data['attachment']);

        $expense = Expense::query()->create($data);

        $audit->log('expense.created', 'Recorded expense '.Money::format($expense->amount_minor).': '.$expense->description, $expense);

        return back()->with('success', 'Expense recorded.');
    }

    public function update(Request $request, Expense $expense, MediaService $media, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-inventory');

        $data = $this->validated($request);

        try {
            $data['amount_minor'] = Money::parse($data['amount']);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid expense amount.']);
        }

        if ($request->hasFile('attachment')) {
            $media->delete($expense->attachment_path);
            $data['attachment_path'] = $media->store($request->file('attachment'), 'expenses');
        }

        unset($data['amount'], $data['attachment']);
        $expense->update($data);

        $audit->log('expense.updated', 'Updated expense '.$expense->description.'.', $expense);

        return back()->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense, MediaService $media, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-inventory');

        $media->delete($expense->attachment_path);
        $audit->log('expense.deleted', 'Deleted expense '.$expense->description.'.', null, ['amount_minor' => $expense->amount_minor]);
        $expense->delete();

        return back()->with('success', 'Expense removed.');
    }

    public function storeCategory(Request $request, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-inventory');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:expense_categories,name'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $category = ExpenseCategory::query()->create([...$data, 'is_active' => true]);
        $audit->log('expense_category.created', 'Created expense category '.$category->name.'.', $category);

        return back()->with('success', 'Expense category created.');
    }

    public function updateCategory(Request $request, ExpenseCategory $expenseCategory, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-inventory');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('expense_categories', 'name')->ignore($expenseCategory->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $expenseCategory->update($data);
        $audit->log('expense_category.updated', 'Updated expense category '.$expenseCategory->name.'.', $expenseCategory);

        return back()->with('success', 'Expense category updated.');
    }

    public function destroyCategory(ExpenseCategory $expenseCategory, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-inventory');

        if ($expenseCategory->expenses()->exists()) {
            return back()->with('error', 'This category is used by recorded expenses.');
        }

        $audit->log('expense_category.deleted', 'Deleted expense category '.$expenseCategory->name.'.');
        $expenseCategory->delete();

        return back()->with('success', 'Expense category removed.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'description' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'string', 'max:20'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ]);
    }
}
