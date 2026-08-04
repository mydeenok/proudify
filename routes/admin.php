<?php

use App\Http\Controllers\Admin\AdminAnalyticsController;
use App\Http\Controllers\Admin\AdminCertificateController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\BulkUploadController;
use App\Http\Controllers\Admin\CertificateBuilderController;
use App\Http\Controllers\Admin\ContactRequestController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('analytics', [AdminAnalyticsController::class, 'index'])->name('analytics.index');

    Route::get('certificates', [AdminCertificateController::class, 'index'])->name('certificates.index');
    Route::post('certificates/bulk-download', [AdminCertificateController::class, 'bulkDownload'])->name('certificates.bulk-download');
    Route::patch('certificates/{certificate}/revoke', [AdminCertificateController::class, 'revoke'])->name('certificates.revoke');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/unapproved', [UserController::class, 'unapproved'])->name('users.unapproved');
    Route::patch('users/{user}/approve', [UserController::class, 'approve'])->name('users.approve');
    Route::delete('users/{user}/reject', [UserController::class, 'reject'])->name('users.reject');
    Route::patch('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    Route::patch('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');

    Route::resource('templates', TemplateController::class)->except(['show']);
    Route::patch('templates/{template}/toggle-status', [TemplateController::class, 'toggleStatus'])
        ->name('templates.toggle-status');

    Route::get('templates/{template}/builder', [CertificateBuilderController::class, 'edit'])->name('templates.builder');
    Route::post('templates/{template}/builder/save', [CertificateBuilderController::class, 'save'])->name('templates.builder.save');
    Route::post('templates/{template}/builder/publish', [CertificateBuilderController::class, 'publish'])->name('templates.builder.publish');

    Route::get('bulk-upload', [BulkUploadController::class, 'create'])->name('bulk-upload.create');
    Route::post('bulk-upload', [BulkUploadController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('bulk-upload.store');

    Route::resource('subscriptions', SubscriptionPlanController::class)->except(['show']);
    Route::patch('subscriptions/{subscription}/toggle-status', [SubscriptionPlanController::class, 'toggleStatus'])
        ->name('subscriptions.toggle-status');

    Route::get('user-subscriptions', [UserSubscriptionController::class, 'index'])->name('user-subscriptions.index');
    Route::get('user-subscriptions/{userSubscription}/edit', [UserSubscriptionController::class, 'edit'])->name('user-subscriptions.edit');
    Route::patch('user-subscriptions/{userSubscription}', [UserSubscriptionController::class, 'update'])->name('user-subscriptions.update');
    Route::post('user-subscriptions/{userSubscription}/extend', [UserSubscriptionController::class, 'extend'])->name('user-subscriptions.extend');
    Route::patch('user-subscriptions/{userSubscription}/cancel', [UserSubscriptionController::class, 'cancel'])->name('user-subscriptions.cancel');

    Route::get('contact-requests', [ContactRequestController::class, 'index'])->name('contact-requests.index');
    Route::get('contact-requests/{contactRequest}', [ContactRequestController::class, 'show'])->name('contact-requests.show');
    Route::patch('contact-requests/{contactRequest}/status', [ContactRequestController::class, 'updateStatus'])->name('contact-requests.status');
});
