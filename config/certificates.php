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
    | Canvas Render (Chrome-free)
    |--------------------------------------------------------------------------
    |
    | Certificate PDFs are painted by CertificateCanvasRenderService via
    | Node + @napi-rs/canvas (Skia). No headless Chrome / Puppeteer /
    | Browsershot and no Ubuntu Chromium system libraries are required.
    | Templates must be Visual Builder canvas_json designs — legacy HTML
    | templates need migration before they can issue.
    |
    */

    'canvas_render_timeout' => env('CERTIFICATE_CANVAS_RENDER_TIMEOUT', 30),

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
