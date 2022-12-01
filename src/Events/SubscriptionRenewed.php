<?php

namespace Jojostx\Larasubs\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Model $subscription,
    ) {
      //
    }
}
