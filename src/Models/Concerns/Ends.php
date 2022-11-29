<?php

namespace Jojostx\Larasubs\Models\Concerns;

use Jojostx\Larasubs\Models\Scopes\EndingScope;

trait Ends
{
    public static function bootEnding()
    {
        static::addGlobalScope(new EndingScope());
    }

    public function initializeEnding()
    {
        if (! isset($this->casts['ends_at'])) {
            $this->casts['ends_at'] = 'datetime';
        }
    }

    public function ends()
    {
        return $this->ends_at->isPast();
    }

    public function notEnds()
    {
        return ! $this->ends();
    }
}