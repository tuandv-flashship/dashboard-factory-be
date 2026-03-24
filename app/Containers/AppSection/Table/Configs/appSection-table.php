<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Table Module — Global Configuration
    |--------------------------------------------------------------------------
    |
    | Global settings for the table-meta and form-meta APIs.
    | Per-model config is defined in each container's own `table-models.php`.
    |
    | Convention:
    |   Container/Configs/table-models.php → auto-discovered, merged here.
    |
    */

    'cache_ttl'      => env('TABLE_META_CACHE_TTL', 3600), // seconds, 0 = no cache
    'max_bulk_items' => 100,

    // Models are auto-discovered from container configs (table-models.php).
    // No need to register models here.
    'models' => [],
];
