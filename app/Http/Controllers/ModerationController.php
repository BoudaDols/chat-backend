<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\MessageReport;
use App\Models\BlockedIp;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class ModerationController extends Controller
{
    public function reportMessage(Request $request, Message $message)
    {
        $validated = $request->validate([
            'reason' => 'required|in:spam,harassment,inappropriate,violence,hate_speech,other',
            'description' => 'nullable|string|max:500'
        ]);

        // Check if user already reported this message
        $existingReport = MessageReport::where([
            'message_id' => $message->id,
            'reported_by' => $request->user()->id
        ])->first();

        if ($existingReport) {
            return response()->json(['error' => 'You have already reported this message'], 422);
        }

        $report = MessageReport::create([
            'message_id' => $message->id,
            'reported_by' => $request->user()->id,
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
        ]);

        AuditLog::log('message_reported', $message, null, $validated);

        return response()->json([
            'message' => 'Message reported successfully',
            'report_id' => $report->id
        ]);
    }

    public function getReports(Request $request)
    {
        // Only allow admins/moderators (you can add role checking here)
        $reports = MessageReport::with(['message.user', 'reporter', 'reviewer'])
            ->pending()
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $reports]);
    }

    public function reviewReport(Request $request, MessageReport $report)
    {
        $validated = $request->validate([
            'status' => 'required|in:reviewed,resolved,dismissed',
            'moderator_notes' => 'nullable|string|max:1000'
        ]);

        $oldStatus = $report->status;
        
        $report->update([
            'status' => $validated['status'],
            'reviewed_by' => $request->user()->id,
            'moderator_notes' => $validated['moderator_notes'] ?? null,
            'reviewed_at' => now()
        ]);

        // If resolved, you might want to take action on the message
        if ($validated['status'] === 'resolved') {
            // Example: Mark message as deleted or take other action
            $report->message->update([
                'is_deleted' => true,
                'deleted_at' => now(),
                'content' => 'This message was removed by moderation'
            ]);
            AuditLog::log('message_deleted_by_moderation', $report->message);
        }

        AuditLog::log('report_reviewed', $report, ['status' => $oldStatus], $validated);

        return response()->json(['message' => 'Report reviewed successfully']);
    }

    public function getUserReports(Request $request)
    {
        $reports = MessageReport::where('reported_by', $request->user()->id)
            ->with(['message.user'])
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $reports]);
    }

    public function blockIp(Request $request)
    {
        $validated = $request->validate([
            'ip_address' => 'required|ip',
            'reason' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date|after:now'
        ]);

        $blockedIp = BlockedIp::updateOrCreate(
            ['ip_address' => $validated['ip_address']],
            [
                'blocked_by' => $request->user()->id,
                'reason' => $validated['reason'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'is_active' => true
            ]
        );

        AuditLog::log('ip_blocked', $blockedIp, null, $validated);

        return response()->json(['message' => 'IP address blocked successfully']);
    }

    public function unblockIp(Request $request)
    {
        $validated = $request->validate([
            'ip_address' => 'required|ip'
        ]);

        BlockedIp::where('ip_address', $validated['ip_address'])
                 ->update(['is_active' => false]);

        AuditLog::log('ip_unblocked', null, null, $validated);

        return response()->json(['message' => 'IP address unblocked successfully']);
    }

    public function getBlockedIps(Request $request)
    {
        $blockedIps = BlockedIp::with('blockedBy')
                              ->active()
                              ->latest()
                              ->paginate(20);

        return response()->json(['data' => $blockedIps]);
    }

    public function getAuditLogs(Request $request)
    {
        $logs = AuditLog::with('user')
                       ->latest()
                       ->paginate(50);

        return response()->json(['data' => $logs]);
    }
}