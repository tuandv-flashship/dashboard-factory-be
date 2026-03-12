<?php

return [
    'options' => [
        'drivers' => [
            'public' => 'Công khai',
            'local' => 'Cục bộ',
            's3' => 'Amazon S3',
            'r2' => 'Cloudflare R2',
            'do_spaces' => 'DigitalOcean Spaces',
            'wasabi' => 'Wasabi',
            'bunnycdn' => 'BunnyCDN',
            'backblaze' => 'Backblaze B2',
        ],
        'thumbnail_crop_positions' => [
            'left' => 'Trái',
            'right' => 'Phải',
            'top' => 'Trên',
            'bottom' => 'Dưới',
            'center' => 'Giữa',
        ],
        'watermark_positions' => [
            'top_left' => 'Trên trái',
            'top_right' => 'Trên phải',
            'bottom_left' => 'Dưới trái',
            'bottom_right' => 'Dưới phải',
            'center' => 'Giữa',
        ],
        'image_processing_libraries' => [
            'gd' => 'GD',
        ],
        'boolean' => [
            'on' => 'Bật',
            'off' => 'Tắt',
        ],
        'sizes' => [
            'thumb' => 'Ảnh thu nhỏ',
        ],
    ],
];
