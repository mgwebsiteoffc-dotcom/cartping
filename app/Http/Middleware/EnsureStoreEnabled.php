<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Blocks access for stores that the owner has disabled (disabled_at set).
 * Shows a "store disabled" page instead of letting them use the app.
 */
class EnsureStoreEnabled
{
    public function handle(Request $request, Closure $next)
    {
        $store = Auth::guard('store')->user();

        if ($store && $store->isDisabled()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Store disabled'], 403);
            }

            return response()->view('errors.store_disabled', ['store' => $store], 403);
        }

        return $next($request);
    }
}
