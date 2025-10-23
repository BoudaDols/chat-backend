<?php

namespace Database\Factories;

use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chat_room_id' => ChatRoom::factory(),
            'user_id' => User::factory(),
            'content' => $this->faker->sentence(),
            'type' => 'text',
            'is_edited' => false,
            'is_deleted' => false,
        ];
    }
}