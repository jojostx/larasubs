<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (!function_exists('validateApiKey')) {
    function validateApiKey($apiKey): bool
    {
        if ($apiKey == '' || !is_string($apiKey)) {
            throw new InvalidArgumentException('Api key must be a string and cannot be empty');
        }

        return true;
    }
}

if (!function_exists('generateFlwRef')) {
    /**
     * Generates a unique reference
     */
    function generateFlwRef(string $transactionPrefix = NULL): string
    {
        if ($transactionPrefix) {
            return $transactionPrefix . '_' . uniqid(strval(time()));
        }
        return 'flw_' . uniqid(strval(time()));
    }
}

if (!function_exists('convertArrayToObject')) {
    /**
     * Converts a response from the Flutterwave API to the corresponding PHP object.
     */
    function convertArrayToObject(array $resp): array|object
    {
        if (!is_array($resp)) {
            $message = 'The response passed must be an array';

            throw new InvalidArgumentException($message);
        }

        $object = new stdClass();

        $arrayToObject = function ($array, &$obj) use (&$arrayToObject)
        {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $obj->$key = new stdClass();
                    $arrayToObject($value, $obj->$key);
                } else {
                    $obj->$key = $value;
                }
            }

            return $obj;
        };

        return $arrayToObject($resp, $object);
    }
}

if (!function_exists('getDateDifference')) {
    /**
     * get the difference between two Carbon instances
     */
    function getDateDifference(Carbon $from, Carbon $to, string $unit): int
    {
        $unitInPlural = Str::plural($unit);

        $differenceMethodName = 'diffIn' . $unitInPlural;

        return $from->{$differenceMethodName}($to);
    }
}

if (!function_exists('timezones')) {
    /**
     * Get valid timezones.
     *
     * @return array
     */
    function timezones()
    {
        return array_combine(timezone_identifiers_list(), timezone_identifiers_list());
    }
}

if (!function_exists('timeoffsets')) {
    /**
     * Get valid time offsets.
     *
     * @return array
     */
    function timeoffsets()
    {
        return [
            '-1200' => 'UTC -12:00',
            '-1100' => 'UTC -11:00',
            '-1000' => 'UTC -10:00',
            '-0930' => 'UTC -09:30',
            '-0900' => 'UTC -09:00',
            '-0800' => 'UTC -08:00',
            '-0700' => 'UTC -07:00',
            '-0600' => 'UTC -06:00',
            '-0500' => 'UTC -05:00',
            '-0400' => 'UTC -04:00',
            '-0330' => 'UTC -03:30',
            '-0300' => 'UTC -03:00',
            '-0200' => 'UTC -02:00',
            '-0100' => 'UTC -01:00',
            '+0000' => 'UTC ±00:00',
            '+0100' => 'UTC +01:00',
            '+0200' => 'UTC +02:00',
            '+0300' => 'UTC +03:00',
            '+0330' => 'UTC +03:30',
            '+0400' => 'UTC +04:00',
            '+0430' => 'UTC +04:30',
            '+0500' => 'UTC +05:00',
            '+0530' => 'UTC +05:30',
            '+0545' => 'UTC +05:45',
            '+0600' => 'UTC +06:00',
            '+0630' => 'UTC +06:30',
            '+0700' => 'UTC +07:00',
            '+0800' => 'UTC +08:00',
            '+0845' => 'UTC +08:45',
            '+0900' => 'UTC +09:00',
            '+0930' => 'UTC +09:30',
            '+1000' => 'UTC +10:00',
            '+1030' => 'UTC +10:30',
            '+1100' => 'UTC +11:00',
            '+1200' => 'UTC +12:00',
            '+1245' => 'UTC +12:45',
            '+1300' => 'UTC +13:00',
            '+1400' => 'UTC +14:00',
        ];
    }
}


if (!function_exists('array_search_recursive')) {
    /**
     * Recursively searches the array for a given value and returns the corresponding key if successful.
     */
    function array_search_recursive($needle, array $haystack)
    {
        foreach ($haystack as $key => $value) {
            $current_key = $key;
            if ($needle === $value || (is_array($value) && array_search_recursive($needle, $value) !== false)) {
                return $current_key;
            }
        }

        return false;
    }
}

if (!function_exists('array_trim_recursive')) {
    /**
     * Recursively trim elements of the given array.
     */
    function array_trim_recursive($values, string $charlist = " \t\n\r\0\x0B")
    {
        if (is_array($values)) {
            return array_map('array_trim_recursive', $values);
        }

        return is_string($values) ? trim($values, $charlist) : $values;
    }
}

if (!function_exists('array_filter_recursive')) {
    /**
     * Recursively filter empty strings and null elements of the given array.
     *
     * @param array $values
     * @param bool  $strOnly
     *
     * @return mixed
     */
    function array_filter_recursive($values, $strOnly = true)
    {
        foreach ($values as &$value) {
            if (is_array($value)) {
                $value = array_filter_recursive($value);
            }
        }

        return !$strOnly ? array_filter($values) : array_filter($values, function ($item) {
            return !is_null($item) && !((is_string($item) || is_array($item)) && empty($item));
        });
    }
}

if (!function_exists('supportsJsonColumn')) {
    /**
     * Get jsonable column data type.
     */
    function supportsJsonColumn(string $defaultsTo = "text"): string
    {
        $driverName = DB::getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $dbVersion = DB::getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
        $isOldVersion = version_compare($dbVersion, '5.7.8', 'lt');

        return $driverName === 'mysql' && $isOldVersion ? $defaultsTo : 'json';
    }
}


if (!function_exists('getPivotTableName')) {
    /**
     * generates a valid eloquent pivot table name from two strings
     */
    function getPivotTableName(string $first_table, string $second_table): string
    {
        $fragments = [
            Str::lower(Str::singular(trim($first_table))),
            Str::lower(Str::singular(trim($second_table)))
        ];

        ksort($fragments);

        return implode('_', $fragments);
    }
}
