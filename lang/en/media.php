<?php

return [
    'options' => [
        'drivers' => [
            'public' => 'Public',
            'local' => 'Local',
            's3' => 'Amazon S3',
            'r2' => 'Cloudflare R2',
            'do_spaces' => 'DigitalOcean Spaces',
            'wasabi' => 'Wasabi',
            'bunnycdn' => 'BunnyCDN',
            'backblaze' => 'Backblaze B2',
        ],
        'thumbnail_crop_positions' => [
            'left' => 'Left',
            'right' => 'Right',
            'top' => 'Top',
            'bottom' => 'Bottom',
            'center' => 'Center',
        ],
        'watermark_positions' => [
            'top_left' => 'Top left',
            'top_right' => 'Top right',
            'bottom_left' => 'Bottom left',
            'bottom_right' => 'Bottom right',
            'center' => 'Center',
        ],
        'image_processing_libraries' => [
            'gd' => 'GD',
        ],
        'boolean' => [
            'on' => 'On',
            'off' => 'Off',
        ],
        'sizes' => [
            'thumb' => 'Thumbnail',
        ],
    ],
];
