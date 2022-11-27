<?php

namespace Jojostx\Larasubs\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class EndingScope implements Scope
{
    protected $extensions = [
        'OnlyEnded',
        'WithEnded',
        'WithoutEnded',
    ];

    public function apply(Builder $builder, Model $model)
    {
        $builder->where(
            fn (Builder $query) =>
            $query->where('ends_at', '>', now())
                ->orWhereNull('ends_at')
        );
    }

    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
    }

    protected function addWithEnded(Builder $builder)
    {
        $builder->macro('withEnded', function (Builder $builder, $withEnded = true) {
            if ($withEnded) {
                return $builder->withoutGlobalScope($this);
            }

            return $builder->withoutEnded();
        });
    }

    protected function addWithoutEnded(Builder $builder)
    {
        $builder->macro('withoutEnded', function (Builder $builder) {
            $builder->withoutGlobalScope($this)->where(
                fn (Builder $query) =>
                $query->where('ends_at', '>', now())
                    ->orWhereNull('ends_at')
            );

            return $builder;
        });
    }

    protected function addOnlyEnded(Builder $builder)
    {
        $builder->macro('onlyEnded', function (Builder $builder) {
            $builder->withoutGlobalScope($this)->where(
                fn (Builder $query) =>
                $query->where('ends_at', '<=', now())
                    ->whereNotNull('ends_at')
            );

            return $builder;
        });
    }
}