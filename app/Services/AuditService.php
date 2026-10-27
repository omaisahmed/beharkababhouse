<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    public function log(
        string $action,
        string $description,
        ?Model $subject = null,
        array $meta = [],
        ?User $user = null,
    ): AuditLog {
        $user ??= Auth::user();

        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'meta' => $meta ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
