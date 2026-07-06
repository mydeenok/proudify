<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDF / Image Rendering
    |--------------------------------------------------------------------------
    */

    'image_density' => env('CERTIFICATE_IMAGE_DENSITY', 200),
    'image_quality' => env('CERTIFICATE_IMAGE_QUALITY', 90),
    'image_resize' => env('CERTIFICATE_IMAGE_RESIZE', '1000x'),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Named queues this app dispatches onto. Each queue gets its own worker
    | process (see README for the `queue:work --queue=` invocations) so a
    | backlog in one pipeline never starves the others.
    |
    */

    'queues' => [
        'certificates' => env('CERTIFICATE_QUEUE_NAME', 'certificates'),
        'bulk' => env('BULK_QUEUE_NAME', 'bulk'),
        'mail' => env('MAIL_QUEUE_NAME', 'mail'),
        'webhooks' => env('WEBHOOKS_QUEUE_NAME', 'webhooks'),
        'default' => 'default',
    ],

    'job_retries' => env('CERTIFICATE_JOB_RETRIES', 3),
    'job_timeout' => env('CERTIFICATE_JOB_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Bulk Upload
    |--------------------------------------------------------------------------
    */

    'bulk_upload_min_rows' => env('BULK_UPLOAD_MIN_ROWS', 1),
    'bulk_upload_max_rows' => env('BULK_UPLOAD_MAX_ROWS', 2000),
    'bulk_upload_chunk_size' => env('BULK_UPLOAD_CHUNK_SIZE', 100),

];
