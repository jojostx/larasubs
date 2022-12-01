<?php

namespace Jojostx\Larasubs\Enums;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class IntervalType
{
    public const YEAR = 'year';

    public const MONTH = 'month';

    public const WEEK = 'week';

    public const DAY = 'day';

    public static function getDateDifference(Carbon $from, Carbon $to, string $unit): int
    {
        $unitInPlural = Str::plural($unit);

        $differenceMethodName = 'diffIn' . $unitInPlural;

        return $from->{$differenceMethodName}($to);
    }
}
