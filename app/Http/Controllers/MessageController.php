<?php
namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\Message;
use App\Events\NewMessage;
use App\Events\MessageSent;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(ChatRoom $chatRoom, Request $request)
    {
        // Check if user is a participant
        abort_if(!$chatRoom->participants->contains($request->user()), 403);

        $messages = $chatRoom->messages()
            ->with('user')
            ->latest()
            ->paginate(50);

        return response()->json(['data' => $messages]);
    }

    public function store(ChatRoom $chatRoom, Request $request)
    {
        // Check if user is a participant
        abort_if(!$chatRoom->participants->contains($request->user()), 403);

        $validated = $request->validate([
            'content' => 'required_without:media_url|string',
            'type' => 'required|in:text,image,document,audio',
            'media_url' => 'nullable|string',
            'media_filename' => 'nullable|string',
            'media_size' => 'nullable|integer',
        ]);

        $message = $chatRoom->messages()->create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'] ?? '',
            'type' => $validated['type'],
            'media_url' => $validated['media_url'] ?? null,
            'media_filename' => $validated['media_filename'] ?? null,
            'media_size' => $validated['media_size'] ?? null,
        ]);

        // Load the relationships
        $message->load('user');

        // Broadcast the message to other participants
        // broadcast(new NewMessage($message))->toOthers();
        broadcast(new MessageSent($message))->toOthers();


        return response()->json(['data' => $message], 201);
    }

}