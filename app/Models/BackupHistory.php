<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupHistory extends Model
{
    protected $table = 'backup_history';

    protected $fillable = [
        'filename',
        'path',
        'size_bytes',
        'checksum',
        'type',
        'status',
        'notes',
        'created_by',
        'restored_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'restored_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
