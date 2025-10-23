<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Events\UserStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'bio' => 'sometimes|string|max:500',
            'phone' => 'sometimes|string|max:20',
            'avatar' => 'sometimes|image|max:2048', // 2MB max
        ]);

        $user = $request->user();

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar
            if ($user->avatar_url) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar_url));
            }
            
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar_url'] = Storage::url($path);
            unset($validated['avatar']);
        }

        $user->update($validated);

        return response()->json($user);
    }

    public function updateStatus(Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|in:online,offline,away,busy'
        ]);

        $user = $request->user();
        $user->update([
            'status' => $validated['status'],
            'last_seen' => now()
        ]);

        broadcast(new UserStatusChanged($user, $validated['status']));

        return response()->json(['message' => 'Status updated']);
    }

    public function updatePrivacySettings(Request $request)
    {
        $validated = $request->validate([
            'show_last_seen' => 'boolean',
            'show_online_status' => 'boolean',
            'allow_messages_from_strangers' => 'boolean',
        ]);

        $user = $request->user();
        $user->update(['privacy_settings' => $validated]);

        return response()->json(['message' => 'Privacy settings updated']);
    }

    public function blockUser(Request $request, User $user)
    {
        $currentUser = $request->user();
        
        if ($currentUser->id === $user->id) {
            return response()->json(['error' => 'Cannot block yourself'], 422);
        }

        $currentUser->blockedUsers()->syncWithoutDetaching([$user->id]);

        return response()->json(['message' => 'User blocked']);
    }

    public function unblockUser(Request $request, User $user)
    {
        // amazonq-ignore-next-line
        $request->user()->blockedUsers()->detach($user->id);

        return response()->json(['message' => 'User unblocked']);
    }

    public function blockedUsers(Request $request)
    {
        $blockedUsers = $request->user()->blockedUsers()->select('id', 'name', 'avatar_url')->get();

        return response()->json($blockedUsers);
    }

    public function searchUsers(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2'
        ]);

        $currentUser = $request->user();
        $blockedUserIds = $currentUser->blockedUsers()->pluck('users.id');

        $users = User::where('name', 'like', '%' . $validated['query'] . '%')
            ->where('id', '!=', $currentUser->id)
            ->whereNotIn('id', $blockedUserIds)
            ->select('id', 'name', 'avatar_url', 'status', 'last_seen')
            ->limit(20)
            ->get();

        return response()->json($users);
    }
}