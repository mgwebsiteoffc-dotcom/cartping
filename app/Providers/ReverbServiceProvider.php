<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Laravel Reverb is enabled automatically via the reverb config + the
 * package's own provider. This provider wires the Reverb console command
 * registration and any app-level broadcast helpers.
 */
class ReverbServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Laravel\Reverb\Console\Commands\StartServer::class,
            ]);
        }
    }
}
