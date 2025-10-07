<?php
namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\MessageReaction;
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
            ->notDeleted()
            ->with(['user', 'reactions.user', 'replyTo.user'])
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
            'reply_to_id' => 'nullable|exists:messages,id',
        ]);

        $message = $chatRoom->messages()->create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'] ?? '',
            'type' => $validated['type'],
            'media_url' => $validated['media_url'] ?? null,
            'media_filename' => $validated['media_filename'] ?? null,
            'media_size' => $validated['media_size'] ?? null,
            'reply_to_id' => $validated['reply_to_id'] ?? null,
        ]);

        // Load the relationships
        $message->load(['user', 'replyTo.user']);

        // Broadcast the message to other participants
        // broadcast(new NewMessage($message))->toOthers();
        broadcast(new MessageSent($message))->toOthers();


        return response()->json(['data' => $message], 201);
    }

    public function update(Request $request, ChatRoom $chatRoom, Message $message)
    {
        abort_if(!$chatRoom->participants->contains($request->user()), 403);
        abort_if($message->user_id !== $request->user()->id, 403);
        abort_if($message->is_deleted, 422, 'Cannot edit deleted message');

        $validated = $request->validate([
            'content' => 'required|string'
        ]);

        $message->update([
            'content' => $validated['content'],
            'is_edited' => true,
            'edited_at' => now()
        ]);

        $message->load(['user', 'reactions.user', 'replyTo.user']);
        broadcast(new MessageSent($message));

        return response()->json(['data' => $message]);
    }

    public function destroy(Request $request, ChatRoom $chatRoom, Message $message)
    {
        abort_if(!$chatRoom->participants->contains($request->user()), 403);
        abort_if($message->user_id !== $request->user()->id, 403);

        $message->update([
            'is_deleted' => true,
            'deleted_at' => now(),
            'content' => 'This message was deleted'
        ]);

        broadcast(new MessageSent($message));

        return response()->json(['message' => 'Message deleted']);
    }

    public function addReaction(Request $request, ChatRoom $chatRoom, Message $message)
    {
        abort_if(!$chatRoom->participants->contains($request->user()), 403);

        $validated = $request->validate([
            'emoji' => 'required|string|max:10'
        ]);

        $reaction = MessageReaction::updateOrCreate(
            [
                'message_id' => $message->id,
                'user_id' => $request->user()->id,
                'emoji' => $validated['emoji']
            ]
        );

        $message->load(['reactions.user']);
        broadcast(new MessageSent($message));

        return response()->json(['data' => $reaction]);
    }

    public function removeReaction(Request $request, ChatRoom $chatRoom, Message $message)
    {
        abort_if(!$chatRoom->participants->contains($request->user()), 403);

        $validated = $request->validate([
            'emoji' => 'required|string|max:10'
        ]);

        MessageReaction::where([
            'message_id' => $message->id,
            'user_id' => $request->user()->id,
            'emoji' => $validated['emoji']
        ])->delete();

        $message->load(['reactions.user']);
        broadcast(new MessageSent($message));

        return response()->json(['message' => 'Reaction removed']);
    }

    public function search(Request $request, ChatRoom $chatRoom)
    {
        abort_if(!$chatRoom->participants->contains($request->user()), 403);

        $validated = $request->validate([
            'query' => 'required|string|min:2'
        ]);

        $messages = $chatRoom->messages()
            ->notDeleted()
            ->where('content', 'like', '%' . $validated['query'] . '%')
            ->with(['user', 'reactions.user', 'replyTo.user'])
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $messages]);
    }

    public function forward(Request $request, ChatRoom $fromChatRoom, Message $message)
    {
        abort_if(!$fromChatRoom->participants->contains($request->user()), 403);
        abort_if($message->is_deleted, 422, 'Cannot forward deleted message');

        $validated = $request->validate([
            'chat_room_ids' => 'required|array',
            'chat_room_ids.*' => 'exists:chat_rooms,id'
        ]);

        $forwardedMessages = [];

        foreach ($validated['chat_room_ids'] as $chatRoomId) {
            $chatRoom = ChatRoom::find($chatRoomId);
            
            if ($chatRoom->participants->contains($request->user())) {
                $forwardedMessage = $chatRoom->messages()->create([
                    'user_id' => $request->user()->id,
                    'content' => $message->content,
                    'type' => $message->type,
                    'media_url' => $message->media_url,
                    'media_filename' => $message->media_filename,
                    'media_size' => $message->media_size,
                ]);

                $forwardedMessage->load('user');
                broadcast(new MessageSent($forwardedMessage));
                $forwardedMessages[] = $forwardedMessage;
            }
        }

        return response()->json(['data' => $forwardedMessages]);
    }

}