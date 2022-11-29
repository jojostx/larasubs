<?php

namespace Jojostx\Larasubs\Models\Concerns;

use Jojostx\Larasubs\Models\Scopes\CancellingScope;

trait Cancels
{
    public static function bootCancelling()
    {
        static::addGlobalScope(new CancellingScope());
    }

    public function initializeCancelling()
    {
        if (! isset($this->casts['cancels_at'])) {
            $this->casts['cancels_at'] = 'datetime';
        }
    }

    public function cancelled()
    {
        if (empty($this->cancels_at)) {
            return false;
        }

        return $this->cancels_at->isPast();
    }

    public function notCancelled()
    {
        return ! $this->cancelled();
    }
}