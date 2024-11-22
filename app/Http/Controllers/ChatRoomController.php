<?php
namespace App\Http\Controllers;

use App\Models\ChatRoom;
use Illuminate\Http\Request;

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
            'participant_ids' => 'required|array',
            'participant_ids.*' => 'exists:users,id',
        ]);

        $chatRoom = ChatRoom::create([
            'name' => $validated['name'],
            'type' => $validated['type'],
        ]);

        // Add participants including the current user
        $participantIds = array_unique([
            $request->user()->id,
            ...$validated['participant_ids'],
        ]);
        
        $chatRoom->participants()->attach($participantIds);

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
}