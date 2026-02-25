<?php

namespace App\Models\Integrations;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LuceedSyncRun extends Model
{
    protected $fillable = [
        'action_key',
        'action_label',
        'status',
        'summary',
        'stats',
        'error_message',
        'started_at',
        'finished_at',
        'initiated_by',
    ];

    protected $casts = [
        'stats' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'initiated_by' => 'int',
    ];

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
