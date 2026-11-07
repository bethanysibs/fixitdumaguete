<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Booking
    |--------------------------------------------------------------------------
    |
    | The commission rate is a percentage stored as a decimal string.
    | Amounts are calculated in cents so prices never pass through floats.
    |
    */

    'commission_rate' => '10.00',

    'statuses' => [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'in_progress' => 'In progress',
        'completed' => 'Done',
        'canceled' => 'Canceled',
    ],

];
