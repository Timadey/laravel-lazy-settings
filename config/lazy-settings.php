<?php

declare(strict_types=1);

namespace Timadey\LazySettings;

return [
    /*
    |--------------------------------------------------------------------------
    | Coercive mode
    |--------------------------------------------------------------------------
    | When true, values that don't match the enum's declared type are coerced
    | best-effort instead of throwing an InvalidArgumentException. Individual
    | stores can also opt-out via their own $coerce flag.
    */
    'coerce' => false,

    /*
    |--------------------------------------------------------------------------
    | Store location
    |--------------------------------------------------------------------------
    | Relative app path (or namespace segment) where make:settings-store writes
    | new store classes.
    */
    'store_path' => 'Models',

    /*
    |--------------------------------------------------------------------------
    | Enum location
    |--------------------------------------------------------------------------
    | Relative app path (or namespace segment) where make:settings-store writes
    | new setting enums. Kept separate from store_path so codegen matches a
    | typical app layout: stores in App\Models, enums in App\Enums.
    */
    'enum_path' => 'Enums',

    'cache' => [
        /*
        | Cache store to use for the flat settings maps. Null = app default.
        */
        'store' => null,

        /*
        | TTL (seconds) before a cached settings map is re-read. Default 10 days.
        */
        'ttl' => 864000,
    ],
];