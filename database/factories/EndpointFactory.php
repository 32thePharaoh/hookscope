<?php

namespace Database\Factories;

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Endpoint>
 */
class EndpointFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'token' => bin2hex(random_bytes(32)),
            'retention_days' => 7,
        ];
    }
}
