<?php

namespace Jojostx\Larasubs\Models\Concerns;

use Illuminate\Support\Carbon;
use Jojostx\Larasubs\Services\Period;

trait HandlesRecurrence
{
    public function calculateNextRecurrenceEnd(Carbon|string $recurrenceStart = null): Carbon
    {
        $period = new Period($this->interval_type, $this->interval, $recurrenceStart);

        return $period->getEndDate();
    }
}