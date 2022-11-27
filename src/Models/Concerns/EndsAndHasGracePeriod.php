<?php

namespace Jojostx\Larasubs\Models\Concerns;

use Jojostx\Larasubs\Models\Scopes\EndsWithGracePeriodScope;

trait EndsAndHasGracePeriod
{
    public static function bootExpiresAndHasGracePeriod()
    {
        static::addGlobalScope(new EndsWithGracePeriodScope());
    }

    public function initializeExpiresAndHasGracePeriod()
    {
        if (! isset($this->casts['ends_at'])) {
            $this->casts['ends_at'] = 'datetime';
        }

        if (! isset($this->casts['grace_period_ends_at'])) {
            $this->casts['grace_period_ends_at'] = 'datetime';
        }
    }

    public function ended()
    {
        if (is_null($this->grace_period_ends_at)) {
            return $this->ends_at->isPast();
        }

        return $this->ends_at->isPast()
            && $this->grace_period_ends_at->isPast();
    }

    public function notEnded()
    {
        return ! $this->ended();
    }

    public function hasEnded()
    {
        return $this->ended();
    }

    public function hasNotEnded()
    {
        return $this->notEnded();
    }
}