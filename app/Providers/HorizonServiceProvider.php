<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

class HorizonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Dashboard auth: only authenticated merchant stores.
        Horizon::auth(function ($request) {
            return $request->user('store') !== null;
        });
    }
}
