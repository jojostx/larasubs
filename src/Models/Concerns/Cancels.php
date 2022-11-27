<?php

namespace Jojostx\Larasubs\Models\Concerns;

use Jojostx\Larasubs\Models\Scopes\CancellingScope;

trait Cancels
{
    public static function bootCancels()
    {
        static::addGlobalScope(new CancellingScope());
    }

    public function initializeCancels()
    {
        if (! isset($this->casts['cancelled_at'])) {
            $this->casts['cancelled_at'] = 'datetime';
        }
    }

    public function cancelled()
    {
        if (empty($this->cancelled_at)) {
            return false;
        }

        return $this->cancelled_at->isPast();
    }

    public function notCancelled()
    {
        return ! $this->cancelled();
    }
}