<?php

namespace Database\Factories;

use App\Models\ApiCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiCredential>
 */
class ApiCredentialFactory extends Factory
{
    protected $model = ApiCredential::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service' => fake()->randomElement(['runcloud', 'cloudflare', 'gsc']),
            'account_name' => fake()->optional()->safeEmail(),
            'token_data_encrypted' => fake()->uuid(),
        ];
    }

    public function internetArchive(): static
    {
        return $this->state(fn (): array => [
            'service' => 'internet_archive',
            'account_name' => 'Global Internet Archive settings',
            'token_data_encrypted' => json_encode([
                'allowed_hosts' => ['archive.org', 'archive-it.org'],
                'timeout' => 20,
                'poll_delay' => 60,
                'max_attempts' => 1440,
            ], JSON_THROW_ON_ERROR),
        ]);
    }
}
