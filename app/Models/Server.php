<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'name',
        'ip_address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'server_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class, 'server_id', 'server_id');
    }
}
