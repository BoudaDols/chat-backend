<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function setup(Request $request)
    {
        $user = $request->user();
        
        if ($user->two_factor_enabled) {
            return response()->json(['error' => '2FA is already enabled'], 422);
        }

        // Generate secret key
        $secret = $this->google2fa->generateSecretKey();
        
        // Generate QR code URL
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        // Store encrypted secret temporarily (not enabled yet)
        $user->update(['two_factor_secret' => Crypt::encrypt($secret)]);

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'message' => 'Scan the QR code with your authenticator app, then verify with a code to enable 2FA'
        ]);
    }

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6'
        ]);

        $user = $request->user();
        
        if (!$user->two_factor_secret) {
            return response()->json(['error' => 'Please setup 2FA first'], 422);
        }

        $secret = Crypt::decrypt($user->two_factor_secret);
        
        if (!$this->google2fa->verifyKey($secret, $validated['code'])) {
            return response()->json(['error' => 'Invalid verification code'], 422);
        }

        // Enable 2FA
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_enabled_at' => now()
        ]);

        return response()->json(['message' => '2FA enabled successfully']);
    }

    public function disable(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6'
        ]);

        $user = $request->user();
        
        if (!$user->two_factor_enabled) {
            return response()->json(['error' => '2FA is not enabled'], 422);
        }

        $secret = Crypt::decrypt($user->two_factor_secret);
        
        if (!$this->google2fa->verifyKey($secret, $validated['code'])) {
            return response()->json(['error' => 'Invalid verification code'], 422);
        }

        // Disable 2FA
        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_enabled_at' => null
        ]);

        return response()->json(['message' => '2FA disabled successfully']);
    }

    public function status(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'enabled' => $user->two_factor_enabled,
            'enabled_at' => $user->two_factor_enabled_at
        ]);
    }
}