<?php

namespace Tests\Feature;

use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_send_message()
    {
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create();
        $chatRoom->participants()->attach($user->id, ['role' => 'member']);

        $response = $this->actingAs($user)
                        ->postJson("/api/chat-rooms/{$chatRoom->id}/messages", [
                            'content' => 'Hello, world!',
                            'type' => 'text'
                        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => ['id', 'content', 'type', 'user']
                ]);

        $this->assertDatabaseHas('messages', [
            'chat_room_id' => $chatRoom->id,
            'user_id' => $user->id,
            'content' => 'Hello, world!',
            'type' => 'text'
        ]);
    }

    public function test_non_participant_cannot_send_message()
    {
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create();

        $response = $this->actingAs($user)
                        ->postJson("/api/chat-rooms/{$chatRoom->id}/messages", [
                            'content' => 'Hello, world!',
                            'type' => 'text'
                        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_view_messages()
    {
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create();
        $chatRoom->participants()->attach($user->id, ['role' => 'member']);
        
        Message::factory()->count(3)->create([
            'chat_room_id' => $chatRoom->id,
            'user_id' => $user->id
        ]);

        $response = $this->actingAs($user)
                        ->getJson("/api/chat-rooms/{$chatRoom->id}/messages");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'data' => [
                            '*' => ['id', 'content', 'type', 'user']
                        ]
                    ]
                ]);
    }

    public function test_user_can_edit_their_message()
    {
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create();
        $chatRoom->participants()->attach($user->id, ['role' => 'member']);
        
        $message = Message::factory()->create([
            'chat_room_id' => $chatRoom->id,
            'user_id' => $user->id,
            'content' => 'Original message'
        ]);

        $response = $this->actingAs($user)
                        ->putJson("/api/chat-rooms/{$chatRoom->id}/messages/{$message->id}", [
                            'content' => 'Edited message'
                        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'content' => 'Edited message',
            'is_edited' => true
        ]);
    }

    public function test_user_cannot_edit_others_message()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create();
        $chatRoom->participants()->attach([$user->id, $otherUser->id], ['role' => 'member']);
        
        $message = Message::factory()->create([
            'chat_room_id' => $chatRoom->id,
            'user_id' => $otherUser->id
        ]);

        $response = $this->actingAs($user)
                        ->putJson("/api/chat-rooms/{$chatRoom->id}/messages/{$message->id}", [
                            'content' => 'Edited message'
                        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_their_message()
    {
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create();
        $chatRoom->participants()->attach($user->id, ['role' => 'member']);
        
        $message = Message::factory()->create([
            'chat_room_id' => $chatRoom->id,
            'user_id' => $user->id
        ]);

        $response = $this->actingAs($user)
                        ->deleteJson("/api/chat-rooms/{$chatRoom->id}/messages/{$message->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'is_deleted' => true,
            'content' => 'This message was deleted'
        ]);
    }

    public function test_user_can_add_reaction_to_message()
    {
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create();
        $chatRoom->participants()->attach($user->id, ['role' => 'member']);
        
        $message = Message::factory()->create([
            'chat_room_id' => $chatRoom->id,
            'user_id' => $user->id
        ]);

        $response = $this->actingAs($user)
                        ->postJson("/api/chat-rooms/{$chatRoom->id}/messages/{$message->id}/reactions", [
                            'emoji' => '👍'
                        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '👍'
        ]);
    }

    public function test_user_can_search_messages()
    {
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create();
        $chatRoom->participants()->attach($user->id, ['role' => 'member']);
        
        Message::factory()->create([
            'chat_room_id' => $chatRoom->id,
            'user_id' => $user->id,
            'content' => 'Hello world'
        ]);

        $response = $this->actingAs($user)
                        ->getJson("/api/chat-rooms/{$chatRoom->id}/messages/search?query=Hello");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'data' => [
                            '*' => ['id', 'content', 'type', 'user']
                        ]
                    ]
                ]);
    }

    public function test_user_can_forward_message()
    {
        $user = User::factory()->create();
        $chatRoom1 = ChatRoom::factory()->create();
        $chatRoom2 = ChatRoom::factory()->create();
        $chatRoom1->participants()->attach($user->id, ['role' => 'member']);
        $chatRoom2->participants()->attach($user->id, ['role' => 'member']);
        
        $message = Message::factory()->create([
            'chat_room_id' => $chatRoom1->id,
            'user_id' => $user->id,
            'content' => 'Message to forward'
        ]);

        $response = $this->actingAs($user)
                        ->postJson("/api/chat-rooms/{$chatRoom1->id}/messages/{$message->id}/forward", [
                            'chat_room_ids' => [$chatRoom2->id]
                        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('messages', [
            'chat_room_id' => $chatRoom2->id,
            'user_id' => $user->id,
            'content' => 'Message to forward'
        ]);
    }
}