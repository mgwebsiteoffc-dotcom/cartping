<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\Automation\CampaignService;
use Illuminate\Console\Command;

/**
 * Scheduler-driven: dispatches scheduled campaigns whose schedule_at has passed,
 * and re-dispatches in-progress campaigns up to their hourly send limit.
 * Run every minute by the scheduler (cartping:campaigns --dispatch).
 */
class DispatchCampaigns extends Command
{
    protected $signature = 'cartping:campaigns {--dispatch : dispatch due/in-progress campaigns}';

    protected $description = 'Dispatch due scheduled campaigns and continue sending in-progress ones';

    public function handle(CampaignService $campaigns): int
    {
        if (! $this->option('dispatch')) {
            $this->info('Dry-run only. Pass --dispatch to dispatch campaigns.');
            return self::SUCCESS;
        }

        // Scheduled campaigns that are now due.
        $due = Campaign::whereIn('status', ['scheduled'])
            ->where('schedule_at', '<=', now())
            ->get();

        // In-progress campaigns keep sending (respect hourly limit).
        $inProgress = Campaign::where('status', 'sending')->get();

        $dispatched = 0;
        foreach ($due as $campaign) {
            $campaign->update(['status' => 'sending', 'started_at' => now()]);
            $dispatched += $campaigns->dispatchPending($campaign);
        }

        foreach ($inProgress as $campaign) {
            $dispatched += $campaigns->dispatchPending($campaign);
        }

        $this->info("Dispatched {$dispatched} campaign delivery(ies).");

        return self::SUCCESS;
    }
}
