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

    /*
    |--------------------------------------------------------------------------
    | Daily Shift Job Schedule
    |--------------------------------------------------------------------------
    |
    | Time to auto-create shift 1 and fetch tồn đầu ngày from Fplatform.
    | Should run ~10 minutes before the earliest department start time.
    |
    | Format: HH:MM (uses APP_TIMEZONE, default America/Chicago).
    | Default: 05:50 → Ca 1 Pick dept starts at 06:00.
    |
    */

    'daily_shift_job_at' => env('DAILY_SHIFT_JOB_AT', '05:50'),

    /*
    |--------------------------------------------------------------------------
    | Order Inventory Sync Interval
    |--------------------------------------------------------------------------
    |
    | How often (in minutes) to sync order inventory (tồn đơn hàng) from
    | Fplatform into the local order_summaries table.
    |
    | Set to 0 to disable the scheduled sync.
    |
    */

    'order_inventory_sync_interval' => (int) env('ORDER_INVENTORY_SYNC_INTERVAL', 5),

    /*
    |--------------------------------------------------------------------------
    | Hourly Records Sync Interval
    |--------------------------------------------------------------------------
    |
    | How often (in minutes) to sync hourly_records (actual, staff, efficiency)
    | from Fplatform for the current hour slot of the active shift.
    |
    | Set to 0 to disable the scheduled sync.
    |
    */

    'hourly_records_sync_interval' => (int) env('HOURLY_RECORDS_SYNC_INTERVAL', 5),

];
