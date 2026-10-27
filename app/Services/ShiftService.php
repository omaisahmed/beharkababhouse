<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Models\CashierShift;
use App\Models\CashMovement;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftService
{
    public function __construct(protected AuditService $audit) {}

    public function open(User $user, int $openingCashMinor, ?string $notes = null): CashierShift
    {
        if ($openingCashMinor < 0) {
            throw ValidationException::withMessages(['opening_cash' => 'Opening cash cannot be negative.']);
        }

        if ($user->openShift()) {
            throw ValidationException::withMessages(['shift' => 'You already have an open shift.']);
        }

        $shift = CashierShift::query()->create([
            'user_id' => $user->id,
            'status' => ShiftStatus::Open,
            'opening_cash_minor' => $openingCashMinor,
            'opening_notes' => $notes,
            'opened_at' => now(),
        ]);

        $this->audit->log(
            'shift.opened',
            'Opened a cashier shift with '.Money::format($openingCashMinor).' opening cash.',
            $shift,
            ['opening_cash_minor' => $openingCashMinor],
            $user,
        );

        return $shift;
    }

    public function close(CashierShift $shift, int $countedCashMinor, ?string $notes = null, ?User $user = null): CashierShift
    {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages(['shift' => 'This shift is already closed.']);
        }

        if ($countedCashMinor < 0) {
            throw ValidationException::withMessages(['counted_cash' => 'Counted cash cannot be negative.']);
        }

        return DB::transaction(function () use ($shift, $countedCashMinor, $notes, $user) {
            $cashSales = (int) Payment::query()
                ->where('cashier_shift_id', $shift->id)
                ->where('method', PaymentMethod::Cash->value)
                ->where('status', 'completed')
                ->sum('amount_minor');

            $cashRefunds = (int) Refund::query()
                ->where('cashier_shift_id', $shift->id)
                ->where('method', PaymentMethod::Cash->value)
                ->sum('amount_minor');

            $cashIn = (int) CashMovement::query()
                ->where('cashier_shift_id', $shift->id)
                ->where('type', CashMovementType::In->value)
                ->sum('amount_minor');

            $cashOut = (int) CashMovement::query()
                ->where('cashier_shift_id', $shift->id)
                ->where('type', CashMovementType::Out->value)
                ->sum('amount_minor');

            $expected = $shift->opening_cash_minor + $cashSales - $cashRefunds + $cashIn - $cashOut;

            $shift->forceFill([
                'status' => ShiftStatus::Closed,
                'cash_sales_minor' => $cashSales,
                'cash_refunds_minor' => $cashRefunds,
                'cash_in_minor' => $cashIn,
                'cash_out_minor' => $cashOut,
                'expected_cash_minor' => $expected,
                'counted_cash_minor' => $countedCashMinor,
                'difference_minor' => $countedCashMinor - $expected,
                'closing_notes' => $notes,
                'closed_at' => now(),
            ])->save();

            $this->audit->log(
                'shift.closed',
                'Closed shift with '.Money::format($countedCashMinor).' counted and '.Money::format($countedCashMinor - $expected).' variance.',
                $shift,
                ['expected_minor' => $expected, 'counted_minor' => $countedCashMinor, 'difference_minor' => $countedCashMinor - $expected],
                $user,
            );

            return $shift->fresh();
        });
    }

    public function recordMovement(CashierShift $shift, User $user, CashMovementType $type, int $amountMinor, string $reason): CashMovement
    {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages(['shift' => 'Cash movements can only be recorded on an open shift.']);
        }

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
        }

        return DB::transaction(function () use ($shift, $user, $type, $amountMinor, $reason) {
            $movement = $shift->cashMovements()->create([
                'user_id' => $user->id,
                'type' => $type,
                'amount_minor' => $amountMinor,
                'reason' => $reason,
            ]);

            if ($type === CashMovementType::In) {
                $shift->increment('cash_in_minor', $amountMinor);
            } else {
                $shift->increment('cash_out_minor', $amountMinor);
            }

            $this->audit->log(
                'shift.cash_'.$type->value,
                $type->label().' of '.Money::format($amountMinor).' recorded: '.$reason,
                $shift,
                ['amount_minor' => $amountMinor, 'reason' => $reason],
                $user,
            );

            return $movement;
        });
    }

    public function expectedCash(CashierShift $shift): int
    {
        $cashSales = (int) $shift->payments()
            ->where('method', PaymentMethod::Cash->value)
            ->where('status', 'completed')
            ->sum('amount_minor');

        $cashRefunds = (int) $shift->refunds()
            ->where('method', PaymentMethod::Cash->value)
            ->sum('amount_minor');

        $cashIn = (int) $shift->cashMovements()->where('type', CashMovementType::In->value)->sum('amount_minor');
        $cashOut = (int) $shift->cashMovements()->where('type', CashMovementType::Out->value)->sum('amount_minor');

        return $shift->opening_cash_minor + $cashSales - $cashRefunds + $cashIn - $cashOut;
    }
}
