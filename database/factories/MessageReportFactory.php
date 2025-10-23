<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'reported_by' => User::factory(),
            'reason' => $this->faker->randomElement(['spam', 'harassment', 'inappropriate', 'violence', 'hate_speech', 'other']),
            'description' => $this->faker->sentence(),
            'status' => 'pending',
        ];
    }
}