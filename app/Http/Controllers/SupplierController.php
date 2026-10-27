<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-inventory');

        $query = Supplier::query()->withCount(['inventoryItems', 'purchases']);

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('active') && $request->input('active') !== '') {
            $query->where('is_active', $request->boolean('active'));
        }

        $suppliers = $query->orderBy('name')->paginate(25)->withQueryString();

        return view('inventory.suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        $this->authorize('manage-inventory');

        return view('inventory.suppliers.form', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-inventory');

        $supplier = Supplier::query()->create($this->validated($request));

        $audit->log('supplier.created', 'Created supplier '.$supplier->name.'.', $supplier);

        return redirect()->route('suppliers.index')->with('success', 'Supplier created.');
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('manage-inventory');

        return view('inventory.suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-inventory');

        $supplier->update($this->validated($request, $supplier));

        $audit->log('supplier.updated', 'Updated supplier '.$supplier->name.'.', $supplier);

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-inventory');

        if ($supplier->purchases()->exists()) {
            return back()->with('error', 'This supplier has purchases and cannot be removed.');
        }

        $audit->log('supplier.deleted', 'Deleted supplier '.$supplier->name.'.', null, ['name' => $supplier->name]);
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Supplier removed.');
    }

    protected function validated(Request $request, ?Supplier $supplier = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('suppliers', 'name')->ignore($supplier?->id)],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
