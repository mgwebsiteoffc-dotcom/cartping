<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOwnerRole
{
    public function handle(Request $request, Closure $next)
    {
        $store = $request->user('store');

        if ($store && ! $store instanceof \App\Models\Store) {
            // Staff users may not access owner-only routes.
            return redirect()->route('dashboard.index');
        }

        return $next($request);
    }
}
