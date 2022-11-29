<?php

namespace Jojostx\Larasubs\Models\Concerns;

use Jojostx\Larasubs\Models\Scopes\StartingScope;

trait Starts
{
    public static function bootStarting()
    {
        static::addGlobalScope(new StartingScope());
    }

    public function initializeStarting()
    {
        if (! isset($this->casts['starts_at'])) {
            $this->casts['starts_at'] = 'datetime';
        }
    }

    public function started()
    {
        if (empty($this->starts_at)) {
            return false;
        }

        return $this->starts_at->isPast();
    }

    public function notStarted()
    {
        return ! $this->started();
    }
}