<?php

namespace App\Jobs;

use App\Models\FlowRun;
use App\Services\Automation\FlowRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Resumes a flow run after a delay node, or starts a run when triggered.
 */
class ResumeFlow implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public FlowRun $run)
    {
        $this->onQueue('default');
    }

    public function handle(FlowRunner $runner): void
    {
        $run = $this->run;

        if ($run->state === 'completed' || $run->state === 'failed' || $run->state === 'cancelled') {
            return; // already finished
        }

        $flow = $run->flow;
        if (! $flow || ! $flow->is_active) {
            $run->update(['state' => 'cancelled', 'finished_at' => now()]);

            return;
        }

        $store = $flow->store;
        $contact = $run->contact;

        if (! $store || ! $contact) {
            $run->update(['state' => 'failed', 'error' => 'Missing store/contact.']);

            return;
        }

        $runner->run($store, $flow, $contact, $run->conversation, $run);
    }
}
