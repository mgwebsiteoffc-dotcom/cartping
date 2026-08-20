<?php

namespace App\Console\Commands;

use App\Jobs\SendAutomationMessage;
use App\Models\AutomationRun;
use Illuminate\Console\Command;

/**
 * Scheduler-driven: runs any automation whose `due_at` has passed and state is
 * still scheduled. This powers configurable abandoned-cart/browse recovery
 * delays. Invoked every minute by the scheduler (cartping:automations --run).
 */
class RunAutomations extends Command
{
    protected $signature = 'cartping:automations {--run : dispatch due automation runs}';

    protected $description = 'Evaluate and dispatch due automation runs';

    public function handle(): int
    {
        if (! $this->option('run')) {
            $this->info('Dry-run only. Pass --run to dispatch due automations.');
            return self::SUCCESS;
        }

        $due = AutomationRun::where('state', 'scheduled')
            ->where('due_at', '<=', now())
            ->limit(500)
            ->get();

        $count = 0;
        foreach ($due as $run) {
            dispatch(new SendAutomationMessage($run))->onQueue('default');
            $count++;
        }

        $this->info("Dispatched {$count} due automation run(s).");

        return self::SUCCESS;
    }
}
