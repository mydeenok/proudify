<?php

use App\Http\Controllers\Api\CertificateController;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1', LogApiRequest::class])->prefix('v1')->name('api.')->group(function () {
    Route::get('certificates', [CertificateController::class, 'index'])->name('certificates.index');
    Route::get('certificates/{uuid}', [CertificateController::class, 'show'])->name('certificates.show');
    Route::get('certificates/{uuid}/image', [CertificateController::class, 'image'])->name('certificates.image');
    Route::get('certificates/{uuid}/download', [CertificateController::class, 'download'])->name('certificates.download');
    Route::get('certificates/{uuid}/qr', [CertificateController::class, 'qr'])->name('certificates.qr');
});
