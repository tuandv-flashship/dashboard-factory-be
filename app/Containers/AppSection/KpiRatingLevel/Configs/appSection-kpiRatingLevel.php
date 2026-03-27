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
            ['level_name' => 'Xuất sắc',   'bg_color' => '#006400', 'text_color' => '#FFFFFF', 'min_score' => 100, 'operator' => '>=', 'is_kpi_threshold' => false, 'is_staff_warning_threshold' => false, 'sort_order' => 1],
            ['level_name' => 'Đạt',        'bg_color' => '#228B22', 'text_color' => '#FFFFFF', 'min_score' => 95,  'operator' => '>=', 'is_kpi_threshold' => true,  'is_staff_warning_threshold' => false, 'sort_order' => 2],
            ['level_name' => 'Trung bình', 'bg_color' => '#DAA520', 'text_color' => '#FFFFFF', 'min_score' => 90,  'operator' => '>=', 'is_kpi_threshold' => false, 'is_staff_warning_threshold' => true,  'sort_order' => 3],
            ['level_name' => 'Yếu',        'bg_color' => '#8B4513', 'text_color' => '#FFFFFF', 'min_score' => 85,  'operator' => '>=', 'is_kpi_threshold' => false, 'is_staff_warning_threshold' => false, 'sort_order' => 4],
            ['level_name' => 'Chưa đạt',   'bg_color' => '#8B0000', 'text_color' => '#FFFFFF', 'min_score' => 85,  'operator' => '<',  'is_kpi_threshold' => false, 'is_staff_warning_threshold' => false, 'sort_order' => 5],
        ],
    ],
];
