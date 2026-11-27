<?php

return [
    'enabled' => env('ATTENDANCE_REALTIME_ENABLED', false),

    'app_id' => env('ATTENDANCE_REVERB_APP_ID'),
    'key' => env('ATTENDANCE_REVERB_APP_KEY'),
    'secret' => env('ATTENDANCE_REVERB_APP_SECRET'),

    'host' => env(
        'ATTENDANCE_REVERB_HOST',
        'bexia_dev_reverb'
    ),

    'port' => (int) env(
        'ATTENDANCE_REVERB_PORT',
        8080
    ),

    'scheme' => env(
        'ATTENDANCE_REVERB_SCHEME',
        'http'
    ),

    'connect_timeout' => 0.5,
    'timeout' => 1.0,
];
