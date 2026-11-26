<?php

return [

    'enabled' => (bool) env('BEXIA_REPORTS_ENABLED', false),

    'connection' => 'reports_database',

    'queue' => 'reports',

    'block_size' => 150,

    'direct_max_rows' => 150,

    'tested_monthly_rows' => 6000,

    'generation_timeout' => 300,

    'job_timeout' => 360,

    'retry_after' => 420,

    'expires_after_hours' => 24,

    'max_pending_per_user' => 3,

    'max_pending_total' => 20,

    'max_storage_bytes' => 536870912,

    'minimum_free_bytes' => 1073741824,

    'storage_directory' => 'reports',

];
