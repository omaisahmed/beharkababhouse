<?php

namespace App\Http\Controllers;

use App\Enums\CashMovementType;
use App\Models\CashierShift;
use App\Services\SettingsService;
use App\Services\ShiftService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function __construct(
        protected ShiftService $shifts,
        protected SettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $current = $user->openShift();

        $query = CashierShift::query()->with('user')->latest('opened_at');

        if (! $user->isAdmin() && ! $user->isManager()) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user')) {
            $query->where('user_id', $request->integer('user'));
        }

        $shifts = $query->paginate(20)->withQueryString();

        $today = CashierShift::query()
            ->whereDate('opened_at', today())
            ->when(! $user->isAdmin() && ! $user->isManager(), fn ($q) => $q->where('user_id', $user->id))
            ->get();

        $stats = [
            'sales' => (int) $today->sum('cash_sales_minor'),
            'refunds' => (int) $today->sum('cash_refunds_minor'),
            'cash_in' => (int) $today->sum('cash_in_minor'),
            'cash_out' => (int) $today->sum('cash_out_minor'),
            'variance' => (int) $today->sum('difference_minor'),
        ];

        $expected = $current ? $this->shifts->expectedCash($current) : 0;

        return view('shifts.index', compact('current', 'shifts', 'stats', 'expected'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'opening_cash' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $opening = Money::parse($data['opening_cash']);
        } catch (\InvalidArgumentException) {
            return back()->withErrors(['opening_cash' => 'Enter a valid opening cash amount.'])->withInput();
        }

        $this->shifts->open($request->user(), $opening, $data['notes'] ?? null);

        return redirect()->route('shifts.index')->with('success', 'Shift opened. You can start taking orders.');
    }

    public function show(CashierShift $shift): View
    {
        $this->authorizeShift($shift);

        $shift->load(['user', 'payments.order', 'refunds.order', 'cashMovements.user', 'orders']);

        return view('shifts.show', [
            'shift' => $shift,
            'expected' => $this->shifts->expectedCash($shift),
        ]);
    }

    public function close(Request $request, CashierShift $shift): RedirectResponse
    {
        $this->authorizeShift($shift);

        $data = $request->validate([
            'counted_cash' => ['required', 'string', 'max:20'],
            'closing_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $counted = Money::parse($data['counted_cash']);
        } catch (\InvalidArgumentException) {
            return back()->withErrors(['counted_cash' => 'Enter a valid counted cash amount.'])->withInput();
        }

        $closed = $this->shifts->close($shift, $counted, $data['closing_notes'] ?? null, $request->user());

        $message = 'Shift closed. Variance: '.Money::format($closed->difference_minor ?? 0).'.';

        return redirect()->route('shifts.show', $closed)->with('success', $message);
    }

    public function cashMovement(Request $request, CashierShift $shift): RedirectResponse
    {
        abort_unless($shift->user_id === $request->user()->id || $request->user()->isAdmin() || $request->user()->isManager(), 403);

        $data = $request->validate([
            'type' => ['required', Rule::enum(CashMovementType::class)],
            'amount' => ['required', 'string', 'max:20'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        try {
            $amount = Money::parse($data['amount']);
        } catch (\InvalidArgumentException) {
            return back()->withErrors(['amount' => 'Enter a valid amount.'])->withInput();
        }

        $this->shifts->recordMovement(
            $shift,
            $request->user(),
            CashMovementType::from($data['type']),
            $amount,
            $data['reason'],
        );

        return back()->with('success', 'Cash movement recorded.');
    }

    protected function authorizeShift(CashierShift $shift): void
    {
        $user = request()->user();

        abort_unless(
            $user && ($shift->user_id === $user->id || $user->isAdmin() || $user->isManager()),
            403,
        );
    }
}
