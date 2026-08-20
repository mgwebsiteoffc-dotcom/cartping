<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Models\OrderEvent;
use Illuminate\Console\Command;

/**
 * GDPR-friendly retention: purges raw events older than the configured window
 * and old unprocessed webhook payloads.
 */
class PruneData extends Command
{
    protected $signature = 'cartping:prune {--retention=90 : retention days}';

    protected $description = 'Prune raw analytics and webhook events per retention policy';

    public function handle(): int
    {
        $retention = (int) $this->option('retention');
        $cutoff = now()->subDays($retention);

        $analytics = AnalyticsEvent::where('occurred_at', '<', $cutoff)->delete();
        $webhooks = OrderEvent::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$analytics} analytics and {$webhooks} webhook events older than {$retention} days.");

        return self::SUCCESS;
    }
}
