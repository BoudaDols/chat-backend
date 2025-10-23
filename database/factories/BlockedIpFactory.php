<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlockedIpFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ip_address' => $this->faker->ipv4(),
            'blocked_by' => User::factory(),
            'reason' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}