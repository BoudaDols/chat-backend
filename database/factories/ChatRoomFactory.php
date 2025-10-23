<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(['private', 'group']),
            'description' => $this->faker->sentence(),
            'created_by' => User::factory(),
            'settings' => [
                'allow_media' => true,
                'allow_voice_messages' => true,
                'message_retention_days' => 365,
            ],
        ];
    }
}