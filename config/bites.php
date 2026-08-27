<?php

declare(strict_types=1);

return [
    'json_disk' => 'local',
    'model_scan_paths' => [
        app_path(),
        base_path('rimba'),
        base_path('vendor/rimba'),
        base_path('vendor/bit-es'),

        base_path('vendor/spatie'),
    ],
];
