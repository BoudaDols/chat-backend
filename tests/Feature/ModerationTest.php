<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_report_message()
    {
        $user = User::factory()->create();
        $message = Message::factory()->create();

        $response = $this->actingAs($user)
                        ->postJson("/api/messages/{$message->id}/report", [
                            'reason' => 'spam',
                            'description' => 'This is spam content'
                        ]);

        $response->assertStatus(200)
                ->assertJson(['message' => 'Message reported successfully']);

        $this->assertDatabaseHas('message_reports', [
            'message_id' => $message->id,
            'reported_by' => $user->id,
            'reason' => 'spam',
            'status' => 'pending'
        ]);
    }

    public function test_user_cannot_report_same_message_twice()
    {
        $user = User::factory()->create();
        $message = Message::factory()->create();
        
        MessageReport::factory()->create([
            'message_id' => $message->id,
            'reported_by' => $user->id
        ]);

        $response = $this->actingAs($user)
                        ->postJson("/api/messages/{$message->id}/report", [
                            'reason' => 'spam'
                        ]);

        $response->assertStatus(422)
                ->assertJson(['error' => 'You have already reported this message']);
    }

    public function test_moderator_can_view_reports()
    {
        $moderator = User::factory()->create();
        MessageReport::factory()->count(3)->create(['status' => 'pending']);

        $response = $this->actingAs($moderator)
                        ->getJson('/api/moderation/reports');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'data' => [
                            '*' => ['id', 'reason', 'status', 'message', 'reporter']
                        ]
                    ]
                ]);
    }

    public function test_moderator_can_review_report()
    {
        $moderator = User::factory()->create();
        $report = MessageReport::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($moderator)
                        ->putJson("/api/moderation/reports/{$report->id}", [
                            'status' => 'resolved',
                            'moderator_notes' => 'Content removed'
                        ]);

        $response->assertStatus(200)
                ->assertJson(['message' => 'Report reviewed successfully']);

        $this->assertDatabaseHas('message_reports', [
            'id' => $report->id,
            'status' => 'resolved',
            'reviewed_by' => $moderator->id
        ]);
    }

    public function test_admin_can_block_ip()
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)
                        ->postJson('/api/moderation/block-ip', [
                            'ip_address' => '192.168.1.100',
                            'reason' => 'Malicious activity'
                        ]);

        $response->assertStatus(200)
                ->assertJson(['message' => 'IP address blocked successfully']);

        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => '192.168.1.100',
            'blocked_by' => $admin->id,
            'is_active' => true
        ]);
    }

    public function test_admin_can_unblock_ip()
    {
        $admin = User::factory()->create();
        BlockedIp::factory()->create([
            'ip_address' => '192.168.1.100',
            'is_active' => true
        ]);

        $response = $this->actingAs($admin)
                        ->postJson('/api/moderation/unblock-ip', [
                            'ip_address' => '192.168.1.100'
                        ]);

        $response->assertStatus(200)
                ->assertJson(['message' => 'IP address unblocked successfully']);

        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => '192.168.1.100',
            'is_active' => false
        ]);
    }

    public function test_blocked_ip_middleware_blocks_requests()
    {
        BlockedIp::factory()->create([
            'ip_address' => '127.0.0.1',
            'is_active' => true
        ]);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(403)
                ->assertJson(['error' => 'Access denied. Your IP address has been blocked.']);
    }

    public function test_admin_can_view_blocked_ips()
    {
        $admin = User::factory()->create();
        BlockedIp::factory()->count(3)->create(['is_active' => true]);

        $response = $this->actingAs($admin)
                        ->getJson('/api/moderation/blocked-ips');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'data' => [
                            '*' => ['id', 'ip_address', 'reason', 'blocked_by']
                        ]
                    ]
                ]);
    }

    public function test_audit_logs_are_created_for_moderation_actions()
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
             ->postJson('/api/moderation/block-ip', [
                 'ip_address' => '192.168.1.100',
                 'reason' => 'Test block'
             ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'ip_blocked'
        ]);
    }

    public function test_admin_can_view_audit_logs()
    {
        $admin = User::factory()->create();
        AuditLog::factory()->count(5)->create();

        $response = $this->actingAs($admin)
                        ->getJson('/api/moderation/audit-logs');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'data' => [
                            '*' => ['id', 'action', 'user', 'created_at']
                        ]
                    ]
                ]);
    }

    public function test_user_can_view_their_reports()
    {
        $user = User::factory()->create();
        MessageReport::factory()->count(2)->create(['reported_by' => $user->id]);

        $response = $this->actingAs($user)
                        ->getJson('/api/my-reports');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'data' => [
                            '*' => ['id', 'reason', 'status', 'message']
                        ]
                    ]
                ]);
    }
}