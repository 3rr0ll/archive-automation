<?php

namespace App\Models;

use Database\Factories\ApiCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'service', 'account_name', 'token_data_encrypted'])]
#[Hidden(['token_data_encrypted'])]
class ApiCredential extends Model
{
    /** @use HasFactory<ApiCredentialFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token_data_encrypted' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Decrypted token payload for admin responses.
     */
    public function tokenData(): ?string
    {
        return $this->token_data_encrypted;
    }
}
