<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use Closure;
use Illuminate\Http\Request;

class CheckBlockedIp
{
    public function handle(Request $request, Closure $next)
    {
        $ipAddress = $request->ip();
        
        if (BlockedIp::isBlocked($ipAddress)) {
            return response()->json([
                'error' => 'Access denied. Your IP address has been blocked.'
            ], 403);
        }

        return $next($request);
    }
}