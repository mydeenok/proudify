<?php

use App\Http\Controllers\BulkUploadController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PricingController;
use App\Models\Template;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicVerificationController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UserSubscriptionController;
use App\Http\Controllers\Webhooks\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('dashboard');
    }

    $featuredTemplates = Schema::hasTable('templates')
        ? Template::active()
            ->whereNotNull('thumbnail_path')
            ->latest()
            ->limit(6)
            ->get()
        : collect();

    return view('welcome', compact('featuredTemplates'));
});

Route::get('/certificates/verify/{uuid}/{code}', [PublicVerificationController::class, 'show'])
    ->name('certificates.verify');
Route::get('/certificates/verify/{uuid}/{code}/download', [PublicVerificationController::class, 'download'])
    ->name('certificates.verify.download');

Route::get('/pricing', [PricingController::class, 'index'])->name('pricing');

Route::post('/webhooks/razorpay', [RazorpayWebhookController::class, 'handle'])->name('webhooks.razorpay');

Route::middleware(['auth', 'approved'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/organization', [ProfileController::class, 'updateOrganization'])->name('profile.organization.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');

    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
    Route::get('/certificates/create', [CertificateController::class, 'create'])->name('certificates.create');
    Route::get('/certificates/create/{template}/canvas', [CertificateController::class, 'createCanvas'])->name('certificates.create.canvas');
    Route::post('/certificates/preview', [CertificateController::class, 'preview'])
        ->middleware('throttle:60,1')
        ->name('certificates.preview');
    Route::post('/certificates/preview/render', [CertificateController::class, 'previewRender'])
        ->middleware('throttle:60,1')
        ->name('certificates.preview.render');
    Route::post('/certificates', [CertificateController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('certificates.store');
    Route::get('/certificates/{certificate:uuid}', [CertificateController::class, 'show'])->name('certificates.show');
    Route::get('/certificates/{certificate:uuid}/status', [CertificateController::class, 'status'])->name('certificates.status');
    Route::post('/certificates/{certificate:uuid}/regenerate', [CertificateController::class, 'regenerate'])
        ->middleware('throttle:10,1')
        ->name('certificates.regenerate');
    Route::get('/certificates/{certificate:uuid}/download', [CertificateController::class, 'download'])->name('certificates.download');

    Route::prefix('bulk-upload')->name('bulk-upload.')->group(function () {
        Route::get('/', [BulkUploadController::class, 'selectTemplate'])->name('select-template');
        Route::get('/create', [BulkUploadController::class, 'create'])->name('create');
        Route::post('/', [BulkUploadController::class, 'store'])->middleware('throttle:10,1')->name('store');
        Route::get('/{batch}/map-columns', [BulkUploadController::class, 'mapColumns'])->name('map-columns');
        Route::post('/{batch}/map-columns', [BulkUploadController::class, 'storeMapping'])->name('map-columns.store');
        Route::get('/{batch}/review', [BulkUploadController::class, 'review'])->name('review');
        Route::post('/{batch}/confirm', [BulkUploadController::class, 'confirm'])->name('confirm');
        Route::get('/{batch}/status', [BulkUploadController::class, 'status'])->name('status');
        Route::get('/{batch}/status-data', [BulkUploadController::class, 'statusData'])->name('status-data');
        Route::get('/{batch}/error-report', [BulkUploadController::class, 'downloadErrorReport'])->name('error-report');
    });

    Route::get('/subscriptions', [UserSubscriptionController::class, 'index'])->name('subscriptions.index');

    Route::prefix('purchase/{subscription}')->name('purchase.')->group(function () {
        Route::get('/', [PurchaseController::class, 'show'])->name('show');
        Route::post('/', [PurchaseController::class, 'process'])->name('process');
        Route::post('/create-order', [PurchaseController::class, 'createOrder'])->name('create-order');
        Route::post('/verify-payment', [PurchaseController::class, 'verifyPayment'])->name('verify-payment');
    });
});

require __DIR__.'/auth.php';
