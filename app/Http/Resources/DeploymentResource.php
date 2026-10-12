<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeploymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'server_id' => $this->server_id,
            'server' => $this->whenLoaded('server', fn (): array => [
                'id' => $this->server->id,
                'server_id' => $this->server->server_id,
                'name' => $this->server->name,
                'ip_address' => $this->server->ip_address,
            ]),
            'domain' => $this->domain,
            'version' => $this->version instanceof \BackedEnum ? $this->version->value : $this->version,
            'archive_source' => $this->archive_source,
            'archive_original_name' => $this->archive_original_name,
            'archive_status' => $this->archive_status instanceof \BackedEnum ? $this->archive_status->value : $this->archive_status,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'current_step' => $this->current_step,
            'logs' => $this->whenLoaded('logs', fn () => $this->logs->map(fn ($log): array => [
                'id' => $log->id,
                'step_name' => $log->step_name,
                'status' => $log->status,
                'message' => $log->message,
                'created_at' => $log->created_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
