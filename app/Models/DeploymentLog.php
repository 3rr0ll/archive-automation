<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentLog extends Model
{
    protected $fillable = [
        'step_name',
        'status',
        'message',
    ];

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }
}
