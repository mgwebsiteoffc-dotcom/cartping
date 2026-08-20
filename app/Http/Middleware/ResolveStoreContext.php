<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * For authenticated 'store' guard requests, resolves the active tenant and
 * binds it into the container so services (WhatsappManager, agents, analytics)
 * can rely on `store()` without re-looking it up.
 */
class ResolveStoreContext
{
    public function handle(Request $request, Closure $next)
    {
        $store = Auth::guard('store')->user();

        if ($store instanceof Store) {
            $this->app()->instance(Store::class, $store);
        }

        return $next($request);
    }

    protected function app(): \Illuminate\Contracts\Foundation\Application
    {
        return app();
    }
}
