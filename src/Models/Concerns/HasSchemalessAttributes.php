<?php

namespace Jojostx\Larasubs\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;

trait HasSchemalessAttributes
{
    public function initializeHasSchemalessAttributes()
    {
        $this->casts['description'] = SchemalessAttributes::class;
    }

    public function scopeWithDescription(): Builder
    {
        return $this->description->modelScope();
    }
}
