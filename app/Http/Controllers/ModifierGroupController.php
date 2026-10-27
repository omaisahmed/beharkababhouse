<?php

namespace App\Http\Controllers;

use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Services\AuditService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ModifierGroupController extends Controller
{
    public function index(): View
    {
        $this->authorize('manage-menu');

        $groups = ModifierGroup::query()
            ->with(['modifiers' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('menu.modifiers.index', compact('groups'));
    }

    public function create(): View
    {
        $this->authorize('manage-menu');

        return view('menu.modifiers.form', [
            'group' => new ModifierGroup(['is_active' => true, 'min_selections' => 0, 'max_selections' => 1]),
            'modifiers' => collect(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-menu');

        $group = DB::transaction(function () use ($request) {
            $data = $this->validated($request);
            $rows = $data['modifiers'];
            unset($data['modifiers']);

            $group = ModifierGroup::query()->create($data);
            $this->syncModifiers($group, $rows);

            return $group;
        });

        $audit->log('modifier_group.created', 'Created modifier group '.$group->name.'.', $group);

        return redirect()->route('modifier-groups.index')->with('success', 'Modifier group created.');
    }

    public function edit(ModifierGroup $modifierGroup): View
    {
        $this->authorize('manage-menu');

        return view('menu.modifiers.form', [
            'group' => $modifierGroup->load('modifiers'),
            'modifiers' => $modifierGroup->modifiers,
        ]);
    }

    public function update(Request $request, ModifierGroup $modifierGroup, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-menu');

        DB::transaction(function () use ($request, $modifierGroup) {
            $data = $this->validated($request);
            $rows = $data['modifiers'];
            unset($data['modifiers']);

            $modifierGroup->update($data);
            $this->syncModifiers($modifierGroup, $rows);
        });

        $audit->log('modifier_group.updated', 'Updated modifier group '.$modifierGroup->name.'.', $modifierGroup);

        return redirect()->route('modifier-groups.index')->with('success', 'Modifier group updated.');
    }

    public function destroy(ModifierGroup $modifierGroup, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-menu');

        $audit->log('modifier_group.deleted', 'Deleted modifier group '.$modifierGroup->name.'.', null, ['name' => $modifierGroup->name]);
        $modifierGroup->delete();

        return redirect()->route('modifier-groups.index')->with('success', 'Modifier group removed.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_required' => ['nullable', 'boolean'],
            'min_selections' => ['required', 'integer', 'min:0', 'max:20'],
            'max_selections' => ['required', 'integer', 'min:1', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'modifiers' => ['nullable', 'array'],
            'modifiers.*.id' => ['nullable', 'integer'],
            'modifiers.*.name' => ['nullable', 'string', 'max:80'],
            'modifiers.*.price' => ['nullable', 'string', 'max:20'],
            'modifiers.*.is_active' => ['nullable', 'boolean'],
        ]);

        if ((int) $data['min_selections'] > (int) $data['max_selections']) {
            throw ValidationException::withMessages(['min_selections' => 'Minimum selections cannot exceed the maximum.']);
        }

        $data['is_required'] = $request->boolean('is_required');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $rows = [];

        foreach ($data['modifiers'] ?? [] as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            try {
                $price = Money::parse($row['price'] ?? 0);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['modifiers' => 'Enter valid modifier prices.']);
            }

            $rows[] = [
                'id' => $row['id'] ?? null,
                'name' => $name,
                'price_minor' => $price,
                'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'sort_order' => $index,
            ];
        }

        $data['modifiers'] = $rows;

        return $data;
    }

    protected function syncModifiers(ModifierGroup $group, array $rows): void
    {
        $existing = $group->modifiers()->get()->keyBy('id');
        $kept = [];

        foreach ($rows as $row) {
            $modifier = $row['id'] ? $existing->get((int) $row['id']) : null;
            $modifier ??= new Modifier(['modifier_group_id' => $group->id]);

            $modifier->fill([
                'name' => $row['name'],
                'price_minor' => $row['price_minor'],
                'is_active' => $row['is_active'],
                'sort_order' => $row['sort_order'],
            ]);
            $modifier->modifier_group_id = $group->id;
            $modifier->save();
            $kept[] = $modifier->id;
        }

        $group->modifiers()->whereNotIn('id', $kept)->delete();
    }
}
