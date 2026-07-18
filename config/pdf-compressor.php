<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Upload Size (bytes)
    |--------------------------------------------------------------------------
    |
    | Maximum allowed PDF upload size in bytes. Default is 50MB.
    |
    */

    'max_upload_size' => (int) env('PDF_COMPRESSOR_MAX_UPLOAD_SIZE', 52_428_800),

    /*
    |--------------------------------------------------------------------------
    | Processing Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Maximum time allowed for Ghostscript to process a single PDF.
    |
    */

    'processing_timeout' => (int) env('PDF_COMPRESSOR_TIMEOUT', 900),

    /*
    |--------------------------------------------------------------------------
    | File Expiry Period (days)
    |--------------------------------------------------------------------------
    |
    | Number of days to keep uploaded and compressed files before auto-deletion.
    |
    */

    'expiry_days' => (int) env('PDF_COMPRESSOR_EXPIRY_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | Disk used for storing uploaded and compressed PDFs. Must be a private disk.
    |
    */

    'storage_disk' => env('PDF_COMPRESSOR_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Ghostscript Binary Path
    |--------------------------------------------------------------------------
    |
    | Full path to the Ghostscript binary. Leave null to auto-detect.
    |
    */

    'ghostscript_path' => env('GHOSTSCRIPT_PATH', null),

    /*
    |--------------------------------------------------------------------------
    | qpdf Binary Path
    |--------------------------------------------------------------------------
    |
    | Full path to the qpdf binary. Leave null to auto-detect.
    | qpdf is optional and used for structural optimization after compression.
    |
    */

    'qpdf_path' => env('QPDF_PATH', null),

    /*
    |--------------------------------------------------------------------------
    | Enable qpdf Optimization
    |--------------------------------------------------------------------------
    |
    | When true, compressed PDFs are further optimized with qpdf if available.
    |
    */

    'use_qpdf' => env('PDF_COMPRESSOR_USE_QPDF', true),

    /*
    |--------------------------------------------------------------------------
    | Compression Levels
    |--------------------------------------------------------------------------
    |
    | Each level defines Ghostscript settings for image resolution (dpi),
    | image quality, and compatibility level.
    |
    */

    'levels' => [
        'low' => [
            'label' => 'Low Compression',
            'description' => 'Best visual quality with light compression.',
            'image_resolution' => 150,
            'mono_resolution' => 300,
            'jpeg_quality' => 85,
            'compatibility_level' => '1.5',
        ],

        'recommended' => [
            'label' => 'Recommended',
            'description' => 'Good balance of speed, quality and file size.',
            'image_resolution' => 100,
            'mono_resolution' => 200,
            'jpeg_quality' => 70,
            'compatibility_level' => '1.5',
        ],

        'extreme' => [
            'label' => 'Extreme Compression',
            'description' => 'Smallest file size with reduced image quality.',
            'image_resolution' => 72,
            'mono_resolution' => 150,
            'jpeg_quality' => 45,
            'compatibility_level' => '1.4',
        ],
    ],

];
