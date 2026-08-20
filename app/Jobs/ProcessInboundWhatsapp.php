<?php

namespace App\Jobs;

use App\Data\WhatsappEvent;
use App\Models\Store;
use App\Services\Whatsapp\InboundPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInboundWhatsapp implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public Store $store, public WhatsappEvent $event)
    {
        $this->onQueue('high');
    }

    public function handle(InboundPipeline $pipeline): void
    {
        $pipeline->handle($this->store, $this->event);
    }
}
