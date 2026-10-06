<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    protected $model = Server::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => fake()->unique()->numberBetween(1, 999999),
            'name' => fake()->unique()->words(2, true),
            'ip_address' => fake()->ipv4(),
            'is_active' => true,
        ];
    }
}
