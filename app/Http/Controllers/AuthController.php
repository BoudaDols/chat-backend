<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Events\UserStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => config('app.default_user_status', 'offline'),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
        // return 'Bonjour';
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->update([
            'status' => config('app.login_status', 'online'),
            'last_seen' => now()
        ]);
        broadcast(new UserStatusChanged($user, config('app.login_status', 'online')));
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $user->update([
            'status' => config('app.logout_status', 'offline'),
            'last_seen' => now()
        ]);
        broadcast(new UserStatusChanged($user, config('app.logout_status', 'offline')));
        
        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

}