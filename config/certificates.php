<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDF / Image Rendering
    |--------------------------------------------------------------------------
    */

    // 150 (down from 200) measured ~46% faster on the Imagick conversion
    // step with no visible quality loss for a screen-viewed certificate
    // preview image - the downloadable artifact is still the full-quality
    // PDF, this setting only affects the JPG preview/thumbnail.
    'image_density' => env('CERTIFICATE_IMAGE_DENSITY', 150),
    'image_quality' => env('CERTIFICATE_IMAGE_QUALITY', 90),
    'image_resize' => env('CERTIFICATE_IMAGE_RESIZE', '1000x'),

    /*
    |--------------------------------------------------------------------------
    | Browsershot (PDF rendering)
    |--------------------------------------------------------------------------
    |
    | By default Browsershot launches a brand-new headless Chrome process
    | per PDF - that alone measured ~1-3s of pure launch overhead on this
    | machine, before any actual rendering starts. Setting these env vars
    | points Browsershot at an already-running Chrome instead (started with
    | --remote-debugging-port matching CERTIFICATE_CHROME_PORT), which
    | measured a consistent 30-60% reduction in render time. Left unset,
    | CertificateRenderService falls back to the normal per-request launch -
    | this is opt-in, not required, so a dev machine without a persistent
    | Chrome process running still works exactly as before.
    |
    */

    'chrome_remote_host' => env('CERTIFICATE_CHROME_HOST'),
    'chrome_remote_port' => env('CERTIFICATE_CHROME_PORT', 9222),

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
