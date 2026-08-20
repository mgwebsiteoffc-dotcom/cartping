<?php

namespace App\Providers;

use App\Services\Ai\AgentOrchestrator;
use App\Services\Ai\OpenRouterClient;
use App\Services\Ai\PromptBuilder;
use App\Services\Ai\RagRetriever;
use App\Services\Ai\Tools\ToolRegistry;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenRouterClient::class, function () {
            return new OpenRouterClient(
                baseUrl: config('ai.base_url'),
                apiKey: config('ai.api_key'),
                httpReferer: config('ai.http_referer'),
                siteUrl: config('ai.site_url'),
            );
        });

        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(RagRetriever::class);
        $this->app->singleton(PromptBuilder::class);
        $this->app->singleton(AgentOrchestrator::class);
    }
}
