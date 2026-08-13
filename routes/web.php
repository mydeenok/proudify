<?php

use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\BulkUploadCheckoutController;
use App\Http\Controllers\BulkUploadController;
use App\Http\Controllers\CertificateCheckoutController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dev\MailPreviewController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicVerificationController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UserSubscriptionController;
use App\Http\Controllers\Webhooks\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);

Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

Route::get('/certificates/verify/{uuid}/{code}', [PublicVerificationController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('certificates.verify');
Route::get('/certificates/verify/{uuid}/{code}/download', [PublicVerificationController::class, 'download'])
    ->middleware('throttle:30,1')
    ->name('certificates.verify.download');
Route::get('/certificates/verify/{uuid}/{code}/image', [PublicVerificationController::class, 'image'])
    ->middleware('throttle:30,1')
    ->name('certificates.verify.image');
Route::get('/certificates/verify/{uuid}/{code}/qr', [PublicVerificationController::class, 'qr'])
    ->middleware('throttle:30,1')
    ->name('certificates.verify.qr');

Route::get('/pricing', [PricingController::class, 'index'])->name('pricing');

Route::post('/webhooks/razorpay', [RazorpayWebhookController::class, 'handle'])->name('webhooks.razorpay');

Route::middleware(['auth', 'approved'])->group(function () {
    // Tenant-exclusive: an admin who navigates here gets bounced back to
    // their own dashboard instead of being served this account's own
    // certificate/subscription data as if they were a customer. Everything
    // else in this group (templates, certificates, bulk-upload, profile)
    // is deliberately shared with admin - see their own doc comments.
    Route::middleware('tenant-only')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/organization', [ProfileController::class, 'updateOrganization'])->name('profile.organization.update');
    Route::get('/profile/organization/logo/{index}', [ProfileController::class, 'logo'])->name('profile.organization.logo');
    Route::get('/profile/organization/signature', [ProfileController::class, 'signature'])->name('profile.organization.signature');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/profile/api-tokens', [ApiTokenController::class, 'store'])->name('profile.api-tokens.store');
    Route::delete('/profile/api-tokens/{tokenId}', [ApiTokenController::class, 'destroy'])->name('profile.api-tokens.destroy');

    Route::delete('/profile/sessions/{sessionId}', [SessionController::class, 'destroy'])->name('profile.sessions.destroy');
    Route::delete('/profile/sessions', [SessionController::class, 'destroyOthers'])->name('profile.sessions.destroy-others');

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
    Route::post('/certificates/drafts', [CertificateController::class, 'saveDraft'])
        ->middleware('throttle:60,1')
        ->name('certificates.drafts.save');
    Route::delete('/certificates/drafts', [CertificateController::class, 'destroyDraft'])
        ->middleware('throttle:30,1')
        ->name('certificates.drafts.destroy');
    Route::post('/certificates', [CertificateController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('certificates.store');

    Route::prefix('certificates/checkout/{certificateOrder:uuid}')->name('certificates.checkout.')->group(function () {
        Route::get('/', [CertificateCheckoutController::class, 'show'])->name('show');
        Route::post('/create-order', [CertificateCheckoutController::class, 'createOrder'])
            ->middleware('throttle:20,1')
            ->name('create-order');
        Route::post('/verify-payment', [CertificateCheckoutController::class, 'verifyPayment'])
            ->middleware('throttle:20,1')
            ->name('verify-payment');
    });

    Route::get('/certificates/{certificate:uuid}', [CertificateController::class, 'show'])->name('certificates.show');
    Route::get('/certificates/{certificate:uuid}/status', [CertificateController::class, 'status'])->name('certificates.status');
    Route::post('/certificates/{certificate:uuid}/regenerate', [CertificateController::class, 'regenerate'])
        ->middleware('throttle:10,1')
        ->name('certificates.regenerate');
    Route::get('/certificates/{certificate:uuid}/download', [CertificateController::class, 'download'])->name('certificates.download');
    Route::get('/certificates/{certificate:uuid}/image', [CertificateController::class, 'image'])->name('certificates.image');
    Route::get('/certificates/{certificate:uuid}/qr', [CertificateController::class, 'qr'])->name('certificates.qr');

    Route::prefix('bulk-upload')->name('bulk-upload.')->group(function () {
        Route::get('/', [BulkUploadController::class, 'selectTemplate'])->name('select-template');
        Route::get('/history', [BulkUploadController::class, 'history'])->name('history');
        Route::get('/create', [BulkUploadController::class, 'create'])->name('create');
        Route::post('/', [BulkUploadController::class, 'store'])->middleware('throttle:10,1')->name('store');
        Route::get('/{batch}/map-columns', [BulkUploadController::class, 'mapColumns'])->name('map-columns');
        Route::post('/{batch}/map-columns', [BulkUploadController::class, 'storeMapping'])->name('map-columns.store');
        Route::get('/{batch}/review', [BulkUploadController::class, 'review'])->name('review');
        Route::post('/{batch}/confirm', [BulkUploadController::class, 'confirm'])->name('confirm');

        Route::prefix('checkout/{certificateOrder:uuid}')->name('checkout.')->group(function () {
            Route::get('/', [BulkUploadCheckoutController::class, 'show'])->name('show');
            Route::post('/create-order', [BulkUploadCheckoutController::class, 'createOrder'])
                ->middleware('throttle:20,1')
                ->name('create-order');
            Route::post('/verify-payment', [BulkUploadCheckoutController::class, 'verifyPayment'])
                ->middleware('throttle:20,1')
                ->name('verify-payment');
        });

        Route::get('/{batch}/status', [BulkUploadController::class, 'status'])->name('status');
        Route::get('/{batch}/status-data', [BulkUploadController::class, 'statusData'])->name('status-data');
        Route::get('/{batch}/error-report', [BulkUploadController::class, 'downloadErrorReport'])->name('error-report');
    });

    Route::middleware('tenant-only')->group(function () {
        Route::get('/subscriptions', [UserSubscriptionController::class, 'index'])->name('subscriptions.index');

        Route::prefix('purchase/{subscription}')->name('purchase.')->group(function () {
            Route::get('/', [PurchaseController::class, 'show'])->name('show');
            Route::post('/', [PurchaseController::class, 'process'])->name('process');
            Route::post('/create-order', [PurchaseController::class, 'createOrder'])->name('create-order');
            Route::post('/verify-payment', [PurchaseController::class, 'verifyPayment'])->name('verify-payment');
        });
    });
});

// Plain 'auth' (not 'approved' or 'admin') so both tenant users and admin
// staff can open/clear their own notifications regardless of which
// role-specific gate the rest of their routes sit behind.
Route::middleware('auth')->group(function () {
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
});

// Dev-only mail preview - never registered outside local, so it can't
// leak in production regardless of who's authenticated.
if (app()->environment('local')) {
    Route::prefix('dev/mails')->name('dev.mail-preview.')->group(function () {
        Route::get('/', [MailPreviewController::class, 'index'])->name('index');
        Route::get('/{type}', [MailPreviewController::class, 'show'])->name('show');
    });
}

require __DIR__.'/auth.php';
