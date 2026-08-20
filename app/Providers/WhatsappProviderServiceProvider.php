<?php

namespace App\Providers;

use App\Services\Whatsapp\Providers\MetaCloudProvider;
use App\Services\Whatsapp\Providers\WhatifyProvider;
use App\Services\Whatsapp\WhatsappManager;
use Illuminate\Support\ServiceProvider;

class WhatsappProviderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsappManager::class, function ($app) {
            return new WhatsappManager([
                'meta' => MetaCloudProvider::class,
                'whatify' => WhatifyProvider::class,
            ]);
        });
    }
}
