<?php

namespace App\Services\Whatsapp;

use App\Models\Store;
use App\Models\WhatsappConnection;
use App\Services\Whatsapp\Contracts\WhatsappProvider;
use InvalidArgumentException;

/**
 * Resolves and caches the correct WhatsApp provider driver for a store.
 * The rest of the app calls `app(WhatsappManager::class)->for($store)` and
 * receives a fully configured provider bound to the store's credentials.
 */
class WhatsappManager
{
    /** @var array<string, WhatsappProvider> */
    protected array $instances = [];

    public function __construct(protected array $drivers = [])
    {
    }

    public function providerNameFor(Store $store): string
    {
        return $store->whatsappConnection?->provider ?? config('whatsapp.default', 'meta');
    }

    public function resolve(string $name): WhatsappProvider
    {
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $config = config("whatsapp.providers.{$name}");

        if (! $config || empty($config['driver'])) {
            throw new InvalidArgumentException("Unknown WhatsApp provider [{$name}].");
        }

        $driver = app($config['driver']);

        if (! $driver instanceof WhatsappProvider) {
            throw new InvalidArgumentException("Provider [{$name}] must implement WhatsappProvider.");
        }

        return $this->instances[$name] = $driver;
    }

    /**
     * Provider bound to a store's connection (throws if not connected).
     */
    public function for(Store $store): WhatsappProvider
    {
        $connection = $store->whatsappConnection;

        abort_if(! $connection || ! $connection->is_connected, 409, 'No WhatsApp connection configured for this store.');

        return $this->resolve($connection->provider)->using($connection);
    }

    /**
     * Provider bound to a specific connection (e.g. when validating creds).
     */
    public function forConnection(WhatsappConnection $connection): WhatsappProvider
    {
        return $this->resolve($connection->provider)->using($connection);
    }
}
