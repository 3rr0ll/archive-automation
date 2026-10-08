<?php

namespace App\Http\Resources;

use App\Models\ApiCredential;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApiCredential
 */
class ApiCredentialResource extends JsonResource
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
            'service' => $this->service,
            'account_name' => $this->account_name,
            'has_token' => filled($this->resource->getRawOriginal('token_data_encrypted')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
