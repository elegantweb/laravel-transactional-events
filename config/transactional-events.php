<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Transactional Events Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for configuring the transactional events of your application
    | that should be dispatched if and only if the outer transaction commits.
    | You can enable event namespaces using prefixes such as App\\ as well
    | as setting up events that should not have a transactional behavior.
    |
    */

    'include' => [
        'App\Events',
    ],

    'exclude' => [
        'Illuminate\Database\Events',
        'eloquent.*',
    ],
];
