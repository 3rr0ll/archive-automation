<?php

namespace App\Models;

use App\Enums\ArchiveStatus;
use App\Enums\DeploymentStatus;
use App\Enums\DomainVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deployment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'server_id',
        'runcloud_web_app_id',
        'runcloud_web_app_name',
        'domain',
        'version',
        'archive_source',
        'archive_url',
        'archive_path',
        'archive_original_name',
        'archive_status',
        'status',
        'current_step',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archive_status' => ArchiveStatus::class,
            'status' => DeploymentStatus::class,
            'version' => DomainVersion::class,
            'runcloud_web_app_id' => 'integer',
        ];
    }

    protected $appends = ['hash'];

    public function getHashAttribute(): string
    {
        return \App\Support\UrlSigner::encode($this->id);
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        // Try decoding as token first
        $decodedId = \App\Support\UrlSigner::decode((string) $value);
        if ($decodedId !== null) {
            return $this->where('id', $decodedId)->first();
        }

        // Fallback to numeric id if needed
        if (is_numeric($value)) {
            return $this->where('id', (int) $value)->first();
        }

        return null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id', 'server_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DeploymentLog::class);
    }

    public function apiUsageRecords(): HasMany
    {
        return $this->hasMany(ApiUsageRecord::class);
    }
}
