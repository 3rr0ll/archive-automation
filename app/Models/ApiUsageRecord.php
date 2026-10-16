<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiUsageRecord extends Model
{
    protected $fillable = [
        'deployment_id',
        'provider',
        'operation',
        'method',
        'endpoint',
        'status_code',
        'successful',
        'duration_ms',
        'requested_at',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'requested_at' => 'immutable_datetime',
        ];
    }

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }
}
