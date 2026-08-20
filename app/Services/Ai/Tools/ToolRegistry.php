<?php

namespace App\Services\Ai\Tools;

use App\Models\Store;
use InvalidArgumentException;

/**
 * Registers every available tool and resolves the enabled subset for a store.
 */
class ToolRegistry
{
    /** @var array<string, Tool> */
    protected array $tools = [];

    public function __construct()
    {
        $this->register([
            new OrderTrackingTool(),
            new OrderDetailsTool(),
            new ProductSearchTool(),
            new InventoryCheckTool(),
            new CheckoutLinkTool(),
            new CartRecoveryTool(),
            new ShippingCalculationTool(),
            new DiscountValidationTool(),
            new ReturnInitiationTool(),
            new FaqSearchTool(),
            new StoreInfoTool(),
            new LtvTool(),
            new HelpMenuTool(),
            new EscalateToHumanTool(),
            new ConversationCloseTool(),
        ]);
    }

    /** @param Tool[] $tools */
    public function register(array $tools): void
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    public function all(): array
    {
        return $this->tools;
    }

    public function get(string $name): Tool
    {
        if (! isset($this->tools[$name])) {
            throw new InvalidArgumentException("Unknown tool [{$name}].");
        }

        return $this->tools[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Tool schemas filtered by the store's AgentConfig enabled_tools.
     */
    public function schemasFor(Store $store): array
    {
        $enabled = $store->agentConfig?->enabled_tools ?? array_keys($this->tools);

        return collect($this->tools)
            ->filter(fn (Tool $t) => in_array($t->name(), $enabled, true))
            ->map(fn (Tool $t) => $t->schema())
            ->values()
            ->all();
    }
}
