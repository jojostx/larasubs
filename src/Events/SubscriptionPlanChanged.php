<?php

namespace Jojostx\Larasubs\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Jojostx\Larasubs\Models\Plan;

class SubscriptionPlanChanged
{
  use Dispatchable;
  use InteractsWithSockets;
  use SerializesModels;

  public function __construct(
      public Model $subscription,
      public Plan $old_plan,
      public Plan $new_plan
  ) {
      //
  }
}