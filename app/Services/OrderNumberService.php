<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Purchase;
use App\Models\Refund;
use Illuminate\Support\Carbon;

class OrderNumberService
{
    public function next(?Carbon $date = null): array
    {
        $date ??= now();
        $sequenceDate = $date->toDateString();

        $lastSequence = (int) Order::query()
            ->where('sequence_date', $sequenceDate)
            ->max('daily_sequence');

        $next = $lastSequence + 1;

        return [
            'sequence_date' => $sequenceDate,
            'daily_sequence' => $next,
            'order_number' => 'BKH-'.$date->format('ymd').'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT),
        ];
    }

    public function nextRefundNumber(): string
    {
        $count = Refund::query()->whereDate('created_at', today())->count() + 1;

        return 'RFD-'.now()->format('ymd').'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    public function nextPurchaseNumber(): string
    {
        $count = Purchase::query()->whereDate('created_at', today())->count() + 1;

        return 'PUR-'.now()->format('ymd').'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
