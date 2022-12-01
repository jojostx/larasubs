<?php

namespace Jojostx\Larasubs\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CancellingScope implements Scope
{
    protected $extensions = [
        'WithCancelled',
        'WithoutCancelled',
        'OnlyCancelled',
    ];

    public function apply(Builder $builder, Model $model)
    {
        $builder->whereNull('cancels_at');
    }

    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
    }

    protected function addWithCancelled(Builder $builder)
    {
        $builder->macro('withCancelled', function (Builder $builder, $withCancelled = true) {
            if ($withCancelled) {
                return $builder->withoutGlobalScope($this);
            }

            return $builder->withoutCancelled();
        });
    }

    protected function addWithoutCancelled(Builder $builder)
    {
        $builder->macro('withoutCancelled', function (Builder $builder) {
            $builder->withoutGlobalScope($this)->whereNull('cancels_at');

            return $builder;
        });
    }

    protected function addOnlyCancelled(Builder $builder)
    {
        $builder->macro('onlyCancelled', function (Builder $builder) {
            $builder->withoutGlobalScope($this)->whereNotNull('cancels_at');

            return $builder;
        });
    }
}
