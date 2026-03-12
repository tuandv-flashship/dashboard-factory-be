<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Icon Prefix
    |--------------------------------------------------------------------------
    |
    | The CSS class prefix used by the icon font.
    | For Tabler Icons webfont: "ti ti-"
    |
    */
    'prefix' => 'ti ti-',

    /*
    |--------------------------------------------------------------------------
    | Manifest Path
    |--------------------------------------------------------------------------
    |
    | Path to the JSON manifest file containing available icon names.
    |
    */
    'manifest_path' => app_path('Containers/AppSection/Icon/Resources/icons-manifest.json'),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Cache the icon manifest in memory for faster subsequent requests.
    |
    */
    'cache_ttl' => 60 * 24, // minutes (24 hours)

    /*
    |--------------------------------------------------------------------------
    | Default Pagination
    |--------------------------------------------------------------------------
    */
    'per_page' => 100,
    'max_per_page' => 500,
];
