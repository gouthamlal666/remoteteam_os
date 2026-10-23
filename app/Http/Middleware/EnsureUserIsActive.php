<?php

namespace App\Http\Middleware;

use App\Services\AttendanceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs out users who were deactivated while logged in, and tracks last-seen. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Your account has been deactivated.']);
        }

        if ($user && $user->tracksAttendance() && $request->hasSession()) {
            app(AttendanceService::class)->recordActivity($user, AttendanceService::isBackgroundPoll($request));
        }

        if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinute()))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
