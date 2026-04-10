<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Current Factory
    |--------------------------------------------------------------------------
    |
    | Determines which factory this deployment serves.
    | Valid values: 'FLS' (FlashShip) or 'PD' (PrintDash).
    |
    | Each deployment should have its own .env with FACTORY=FLS or FACTORY=PD.
    | This value controls which production lines, departments, machines,
    | and inventory queries are used.
    |
    */

    'current' => env('FACTORY', 'FLS'),

];
