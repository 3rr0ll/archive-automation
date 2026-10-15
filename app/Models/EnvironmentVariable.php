<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentVariable extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'environments',
        'note',
        'user_id'
    ];

    protected $casts = [
        'value' => 'encrypted',
    ];

    protected $hidden = [
        'value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
