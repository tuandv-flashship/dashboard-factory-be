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
    | Default: 04:50 → Ca 1 Pick dept starts at 06:00.
    |
    */

    'daily_shift_job_at' => env('DAILY_SHIFT_JOB_AT', '04:50'),

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

    /*
    |--------------------------------------------------------------------------
    | Off-Shift Sync Settings
    |--------------------------------------------------------------------------
    |
    | Controls sync behaviour when no active shift is running.
    | The job fires within a time window before/after each shift:
    |
    |   off_shift_sync_interval   — minutes between syncs (0 = disabled)
    |   off_shift_before_minutes  — minutes before shift start to begin syncing
    |   off_shift_after_minutes   — minutes after shift end to keep syncing
    |
    | All values can be overridden dynamically via the scheduler-settings API
    | (stored in the settings table, cached with 1h TTL).
    |
    */

    'off_shift_sync_interval'    => (int) env('OFF_SHIFT_SYNC_INTERVAL', 15),
    'off_shift_before_minutes'   => (int) env('OFF_SHIFT_BEFORE_MINUTES', 120),
    'off_shift_after_minutes'    => (int) env('OFF_SHIFT_AFTER_MINUTES', 180),

];
