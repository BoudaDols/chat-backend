<?php
namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatRoomController extends Controller
{
    public function index(Request $request)
    {
        $chatRooms = $request->user()
            ->chatRooms()
            ->with(['participants', 'lastMessage'])
            ->get();

        return response()->json(['data' => $chatRooms]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:private,group',
            'description' => 'nullable|string|max:500',
            'avatar' => 'nullable|image|max:2048',
            'participant_ids' => 'required|array',
            'participant_ids.*' => 'exists:users,id',
        ]);

        $avatarUrl = null;
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('chat-rooms', 'public');
            $avatarUrl = Storage::url($path);
        }

        $chatRoom = ChatRoom::create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'avatar_url' => $avatarUrl,
            'created_by' => $request->user()->id,
            'settings' => [
                'allow_media' => true,
                'allow_voice_messages' => true,
                'message_retention_days' => 365,
            ],
        ]);

        // Add creator as owner
        $chatRoom->participants()->attach($request->user()->id, ['role' => 'owner']);
        
        // Add other participants as members
        foreach ($validated['participant_ids'] as $participantId) {
            if ($participantId !== $request->user()->id) {
                $chatRoom->participants()->attach($participantId, ['role' => 'member']);
            }
        }

        return response()->json([
            'data' => $chatRoom->load(['participants', 'lastMessage']),
        ], 201);
    }

    public function show(ChatRoom $chatRoom, Request $request)
    {
        // Check if user is a participant
        abort_if(!$chatRoom->participants->contains($request->user()), 403);

        return response()->json([
            'data' => $chatRoom->load(['participants', 'lastMessage']),
        ]);
    }

    public function update(Request $request, ChatRoom $chatRoom)
    {
        abort_if(!$chatRoom->participants->contains($request->user()), 403);
        abort_if(!$chatRoom->isAdmin($request->user()->id), 403, 'Only admins can update chat room');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:500',
            'avatar' => 'sometimes|image|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            if ($chatRoom->avatar_url) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $chatRoom->avatar_url));
            }
            
            $path = $request->file('avatar')->store('chat-rooms', 'public');
            $validated['avatar_url'] = Storage::url($path);
            unset($validated['avatar']);
        }

        $chatRoom->update($validated);

        return response()->json(['data' => $chatRoom->load(['participants', 'lastMessage'])]);
    }

    public function addParticipant(Request $request, ChatRoom $chatRoom)
    {
        abort_if(!$chatRoom->participants->contains($request->user()), 403);
        abort_if(!$chatRoom->isAdmin($request->user()->id), 403, 'Only admins can add participants');

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'sometimes|in:member,admin',
        ]);

        if ($chatRoom->participants->contains($validated['user_id'])) {
            return response()->json(['error' => 'User is already a participant'], 422);
        }

        $chatRoom->participants()->attach($validated['user_id'], [
            'role' => $validated['role'] ?? 'member'
        ]);

        return response()->json(['message' => 'Participant added']);
    }

    public function removeParticipant(Request $request, ChatRoom $chatRoom, User $user)
    {
        abort_if(!$chatRoom->participants->contains($request->user()), 403);
        
        if ($request->user()->id !== $user->id) {
            abort_if(!$chatRoom->isAdmin($request->user()->id), 403, 'Only admins can remove other participants');
        }

        abort_if($chatRoom->isOwner($user->id), 422, 'Cannot remove the owner');

        $chatRoom->participants()->detach($user->id);

        return response()->json(['message' => 'Participant removed']);
    }

    public function muteParticipant(Request $request, ChatRoom $chatRoom, User $user)
    {
        abort_if(!$chatRoom->participants->contains($request->user()), 403);
        abort_if(!$chatRoom->isAdmin($request->user()->id), 403, 'Only admins can mute participants');

        $validated = $request->validate([
            'duration_minutes' => 'nullable|integer|min:1|max:43200',
        ]);

        $mutedUntil = $validated['duration_minutes'] 
            ? now()->addMinutes($validated['duration_minutes'])
            : null;

        $chatRoom->participants()->updateExistingPivot($user->id, [
            'is_muted' => true,
            'muted_until' => $mutedUntil
        ]);

        return response()->json(['message' => 'Participant muted']);
    }

    public function leave(Request $request, ChatRoom $chatRoom)
    {
        abort_if(!$chatRoom->participants->contains($request->user()), 403);
        abort_if($chatRoom->isOwner($request->user()->id), 422, 'Owner cannot leave. Transfer ownership first.');

        $chatRoom->participants()->detach($request->user()->id);

        return response()->json(['message' => 'Left chat room']);
    }

    public function destroy(Request $request, ChatRoom $chatRoom)
    {
        abort_if(!$chatRoom->isOwner($request->user()->id), 403, 'Only owner can delete chat room');

        if ($chatRoom->avatar_url) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $chatRoom->avatar_url));
        }

        $chatRoom->delete();

        return response()->json(['message' => 'Chat room deleted']);
    }
}