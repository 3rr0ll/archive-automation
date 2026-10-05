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
        'domain',
        'version',
        'archive_url',
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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DeploymentLog::class);
    }
}
