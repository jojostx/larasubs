<?php

namespace App\Models;

use Illuminate\Contracts\Database\Eloquent\Castable;

class Price implements Castable
{
    /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @param  array  $arguments
     * @return string
     */
    public static function castUsing(array $arguments)
    {
        return \config('larasubs.plan.price_column_cast');
    }
}