<?php

namespace Tests\Feature;

use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_chat_room()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $participant = User::factory()->create();

        $response = $this->actingAs($user)
                        ->postJson('/api/chat-rooms', [
                            'name' => 'Test Room',
                            'type' => 'group',
                            'description' => 'A test chat room',
                            'participant_ids' => [$participant->id]
                        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id', 'name', 'type', 'description',
                        'participants'
                    ]
                ]);

        $this->assertDatabaseHas('chat_rooms', [
            'name' => 'Test Room',
            'type' => 'group',
            'created_by' => $user->id
        ]);
    }

    public function test_user_can_upload_avatar_when_creating_room()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $participant = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->actingAs($user)
                        ->postJson('/api/chat-rooms', [
                            'name' => 'Test Room',
                            'type' => 'group',
                            'avatar' => $file,
                            'participant_ids' => [$participant->id]
                        ]);

        $response->assertStatus(201);
        Storage::disk('public')->assertExists('chat-rooms/' . $file->hashName());
    }

    public function test_user_can_view_their_chat_rooms()
    {
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create();
        $chatRoom->participants()->attach($user->id, ['role' => 'member']);

        $response = $this->actingAs($user)
                        ->getJson('/api/chat-rooms');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'name', 'type']
                    ]
                ]);
    }

    public function test_user_can_view_specific_chat_room()
    {
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create();
        $chatRoom->participants()->attach($user->id, ['role' => 'member']);

        $response = $this->actingAs($user)
                        ->getJson("/api/chat-rooms/{$chatRoom->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => ['id', 'name', 'type']
                ]);
    }

    public function test_user_cannot_view_chat_room_they_are_not_part_of()
    {
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create();

        $response = $this->actingAs($user)
                        ->getJson("/api/chat-rooms/{$chatRoom->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_update_chat_room()
    {
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create();
        $chatRoom->participants()->attach($user->id, ['role' => 'admin']);

        $response = $this->actingAs($user)
                        ->putJson("/api/chat-rooms/{$chatRoom->id}", [
                            'name' => 'Updated Room Name',
                            'description' => 'Updated description'
                        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('chat_rooms', [
            'id' => $chatRoom->id,
            'name' => 'Updated Room Name'
        ]);
    }

    public function test_non_admin_cannot_update_chat_room()
    {
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create();
        $chatRoom->participants()->attach($user->id, ['role' => 'member']);

        $response = $this->actingAs($user)
                        ->putJson("/api/chat-rooms/{$chatRoom->id}", [
                            'name' => 'Updated Room Name'
                        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_add_participant()
    {
        $admin = User::factory()->create();
        $newUser = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create();
        $chatRoom->participants()->attach($admin->id, ['role' => 'admin']);

        $response = $this->actingAs($admin)
                        ->postJson("/api/chat-rooms/{$chatRoom->id}/participants", [
                            'user_id' => $newUser->id,
                            'role' => 'member'
                        ]);

        $response->assertStatus(200)
                ->assertJson(['message' => 'Participant added']);

        $this->assertTrue($chatRoom->participants->contains($newUser->id));
    }

    public function test_user_can_leave_chat_room()
    {
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create();
        $chatRoom->participants()->attach($user->id, ['role' => 'member']);

        $response = $this->actingAs($user)
                        ->postJson("/api/chat-rooms/{$chatRoom->id}/leave");

        $response->assertStatus(200)
                ->assertJson(['message' => 'Left chat room']);

        $this->assertFalse($chatRoom->fresh()->participants->contains($user->id));
    }

    public function test_owner_can_delete_chat_room()
    {
        $owner = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create(['created_by' => $owner->id]);
        $chatRoom->participants()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAs($owner)
                        ->deleteJson("/api/chat-rooms/{$chatRoom->id}");

        $response->assertStatus(200)
                ->assertJson(['message' => 'Chat room deleted']);

        $this->assertDatabaseMissing('chat_rooms', ['id' => $chatRoom->id]);
    }
}