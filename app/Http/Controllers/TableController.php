<?php

namespace App\Http\Controllers;

use App\Enums\TableStatus;
use App\Models\RestaurantTable;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TableController extends Controller
{
    public function index(): View
    {
        $tables = RestaurantTable::query()
            ->active()
            ->with(['activeOrder' => fn ($query) => $query->with('items')])
            ->ordered()
            ->get()
            ->groupBy(fn (RestaurantTable $table) => $table->zone ?: 'Main floor');

        return view('tables.index', compact('tables'));
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-tables');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:restaurant_tables,name'],
            'zone' => ['nullable', 'string', 'max:60'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $table = RestaurantTable::query()->create($data + ['is_active' => true, 'status' => TableStatus::Available]);

        $audit->log('table.created', 'Added table '.$table->name.'.', $table, $data);

        return back()->with('success', 'Table added.');
    }

    public function update(Request $request, RestaurantTable $table, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-tables');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('restaurant_tables', 'name')->ignore($table->id)],
            'zone' => ['nullable', 'string', 'max:60'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $table->fill($data);
        $table->is_active = (bool) ($data['is_active'] ?? false);
        $table->save();

        $audit->log('table.updated', 'Updated table '.$table->name.'.', $table, $data);

        return back()->with('success', 'Table updated.');
    }

    public function updateStatus(Request $request, RestaurantTable $table, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-tables');

        $data = $request->validate([
            'status' => ['required', Rule::enum(TableStatus::class)],
        ]);

        $table->forceFill(['status' => TableStatus::from($data['status'])])->save();

        $audit->log('table.status', 'Marked table '.$table->name.' as '.$table->status->label().'.', $table, $data);

        return back()->with('success', 'Table status updated.');
    }

    public function destroy(RestaurantTable $table, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-tables');

        if ($table->activeOrder()->exists()) {
            return back()->with('error', 'This table has an open order and cannot be removed.');
        }

        $table->delete();

        $audit->log('table.deleted', 'Removed table '.$table->name.'.');

        return back()->with('success', 'Table removed.');
    }
}
