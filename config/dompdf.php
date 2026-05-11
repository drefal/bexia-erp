<?php

return [
    'show_warnings' => false,
    'public_path' => public_path(),
    'convert_entities' => true,

    'options' => [
        'font_dir' => storage_path('fonts'),
        'font_cache' => storage_path('fonts'),
        'temp_dir' => sys_get_temp_dir(),

        'chroot' => '/var/www/html',

        'allowed_protocols' => [
            'data://' => ['rules' => []],
            'file://' => ['rules' => []],
            'http://' => ['rules' => []],
            'https://' => ['rules' => []],
        ],

        'log_output_file' => storage_path('logs/dompdf.log'),

        'enable_font_subsetting' => false,
        'pdf_backend' => 'CPDF',

        'default_media_type' => 'print',

        'default_paper_size' => 'a4',
        'default_paper_orientation' => 'landscape',
        'default_font' => 'dejavu sans',

        'dpi' => 96,

        'enable_php' => false,
        'enable_javascript' => false,

        'enable_remote' => true,
        'allowed_remote_hosts' => null,

        'font_height_ratio' => 1.1,
        'enable_html5_parser' => true,
    ],
];