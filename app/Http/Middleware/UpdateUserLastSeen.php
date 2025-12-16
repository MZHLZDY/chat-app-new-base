<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UpdateUserLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            if (!$user->last_seen || $user->last_seen->lt(now()->subMinutes(1))) {
                $user->last_seen = now();
                $user->save();
            }
        }
        return $next($request);
    }
}