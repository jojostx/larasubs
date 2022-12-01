<?php

namespace Jojostx\Larasubs\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class StartingScope implements Scope
{
    protected $extensions = [
        'WhereNotStarted',
        'WithNotStarted',
        'WithoutNotStarted',
    ];

    public function apply(Builder $builder, Model $model)
    {
        $builder->where('starts_at', '<=', now());
    }

    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
    }

    protected function addWithNotStarted(Builder $builder)
    {
        $builder->macro('withNotStarted', function (Builder $builder, $withNotStarted = true) {
            if ($withNotStarted) {
                return $builder->withoutGlobalScope($this);
            }

            return $builder->withoutNotStarted();
        });
    }

    protected function addWithoutNotStarted(Builder $builder)
    {
        $builder->macro('withoutNotStarted', function (Builder $builder) {
            $builder->withoutGlobalScope($this)->where('starts_at', '<=', now());

            return $builder;
        });
    }

    protected function addWhereNotStarted(Builder $builder)
    {
        $builder->macro('whereNotStarted', function (Builder $builder) {
            $builder->withoutGlobalScope($this)->where('starts_at', '>', now());

            return $builder;
        });
    }
}
