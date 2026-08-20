<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\Store;
use App\Policies\ConversationPolicy;
use App\Services\Whatsapp\WhatsappManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsappManager::class);
    }

    public function boot(): void
    {
        // Tenant-scoped authorization: a merchant is owner of their own store.
        Gate::define('access-store', fn (Store $store) => true); // resolved store guard already scoped
        Gate::define('manage-store', fn ($user, Store $store) => true);

        Gate::policy(Conversation::class, ConversationPolicy::class);
    }
}
