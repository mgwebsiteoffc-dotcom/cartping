<?php

namespace App\Events;

use App\Models\ClickEvent;
use App\Models\Store;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CtwaClickRecorded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Store $store,
        public ClickEvent $click,
    ) {
    }
}
