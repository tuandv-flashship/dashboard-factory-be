<?php

return [
    /*
    |--------------------------------------------------------------------------
    | KPI Rating Level — Configuration
    |--------------------------------------------------------------------------
    */

    // Default rating level used when no active record exists in DB
    'default' => [
        'name' => 'Mặc định',
        'details' => [
            ['level_name' => 'Đạt',        'bg_color' => '#4CAF50', 'text_color' => '#FFFFFF', 'min_score' => 95, 'operator' => '>=', 'is_kpi_threshold' => true,  'is_staff_warning_threshold' => false, 'sort_order' => 1],
            ['level_name' => 'Trung bình', 'bg_color' => '#FF9800', 'text_color' => '#FFFFFF', 'min_score' => 85, 'operator' => '>=', 'is_kpi_threshold' => false, 'is_staff_warning_threshold' => true,  'sort_order' => 2],
            ['level_name' => 'Không đạt',  'bg_color' => '#F44336', 'text_color' => '#FFFFFF', 'min_score' => 85, 'operator' => '<',  'is_kpi_threshold' => false, 'is_staff_warning_threshold' => false, 'sort_order' => 3],
        ],
    ],
];
