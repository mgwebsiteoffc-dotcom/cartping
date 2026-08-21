<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Restricts routes to platform staff (superadmin / admin) authenticated on the
 * web guard (users table).
 */
class EnsurePlatformOwner
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();

        if (! $user || ! in_array($user->role, ['superadmin', 'admin'], true)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return redirect()->route('owner.login');
        }

        return $next($request);
    }
}
