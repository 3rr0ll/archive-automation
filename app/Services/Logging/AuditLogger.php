<?php

namespace App\Services\Logging;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $action, ?Model $subject = null, array $context = []): void
    {
        Log::channel('audit')->info($action, [
            'actor_id' => Auth::id(),
            'subject_type' => $subject === null ? null : $subject::class,
            'subject_id' => $subject?->getKey(),
            ...$context,
        ]);
    }
}
