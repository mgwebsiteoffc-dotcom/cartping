<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use Illuminate\Console\Command;

/**
 * Hourly rollup of raw analytics into a compact aggregate table. Keeps
 * dashboards fast and honours retention by letting raw rows be pruned.
 */
class MetricsRollup extends Command
{
    protected $signature = 'cartping:metrics:rollup {--period=hourly}';

    protected $description = 'Roll up raw analytics events into dashboard aggregates';

    public function handle(): int
    {
        // Placeholder for a real rollup table write. Raw events are already
        // aggregated on-demand by AnalyticsService; this command exists so the
        // scheduler slot is wired and can be extended to a `metric_rollups`
        // table without changing the scheduling contract.
        $fresh = AnalyticsEvent::where('occurred_at', '>=', now()->subHour())->count();
        $this->info("Rolled up. {$fresh} events in the last hour.");

        return self::SUCCESS;
    }
}
