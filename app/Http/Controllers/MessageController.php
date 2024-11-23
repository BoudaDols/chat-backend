<?php
namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\Message;
use App\Events\NewMessage;
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
            'content' => 'required|string',
            'type' => 'required|in:text,image,file',
        ]);

        $message = $chatRoom->messages()->create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
            'type' => $validated['type'],
        ]);

        // Load the relationships
        $message->load('user');

        // Broadcast the message to other participants
        // broadcast(new NewMessage($message))->toOthers();
        broadcast(new MessageSent($message))->toOthers();


        return response()->json(['data' => $message], 201);
    }

}