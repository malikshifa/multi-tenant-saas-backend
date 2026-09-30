<?php

use App\Http\Controllers\Platform\ActivityLogController;
use App\Http\Controllers\Platform\AuthController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\DomainController;
use App\Http\Controllers\Platform\InvoiceController;
use App\Http\Controllers\Platform\NotificationController;
use App\Http\Controllers\Platform\PaymentController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\SubscriptionController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\Platform\WebhookController;
use Illuminate\Support\Facades\Route;

// Called server-to-server by Stripe/PayPal, so no auth/CSRF — authenticity
// is verified per-request via the gateway's own signature scheme instead.
Route::prefix('v1/webhooks')->middleware('throttle:60,1')->group(function () {
    Route::post('/stripe', [WebhookController::class, 'stripe']);
    Route::post('/paypal', [WebhookController::class, 'paypal']);
});

Route::prefix('v1/platform')->group(function () {

    Route::middleware('throttle:auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::middleware('auth:central')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
            Route::post('/{notification}/read', [NotificationController::class, 'markAsRead']);
        });

        Route::prefix('payments')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])->middleware('central.permission:payments.view');
            Route::post('/', [PaymentController::class, 'store'])->middleware('central.permission:payments.create');
            Route::get('/{payment}', [PaymentController::class, 'show'])->middleware('central.permission:payments.view');
            Route::get('/{payment}/verify', [PaymentController::class, 'verify'])->middleware('central.permission:payments.verify');
            Route::post('/{payment}/refund', [PaymentController::class, 'refund'])->middleware('central.permission:payments.refund');
        });

        Route::get('plans', [PlanController::class, 'index'])->middleware('central.permission:plans.view');
        Route::get('plans/{plan}', [PlanController::class, 'show'])->middleware('central.permission:plans.view');
        Route::post('plans', [PlanController::class, 'store'])->middleware('central.permission:plans.create');
        Route::match(['put', 'patch'], 'plans/{plan}', [PlanController::class, 'update'])->middleware('central.permission:plans.update');
        Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->middleware('central.permission:plans.delete');

        Route::get('subscriptions', [SubscriptionController::class, 'index'])->middleware('central.permission:subscriptions.view');
        Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show'])->middleware('central.permission:subscriptions.view');
        Route::post('subscriptions', [SubscriptionController::class, 'store'])->middleware('central.permission:subscriptions.create');
        Route::match(['put', 'patch'], 'subscriptions/{subscription}', [SubscriptionController::class, 'update'])->middleware('central.permission:subscriptions.update');
        Route::delete('subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])->middleware('central.permission:subscriptions.cancel');

        Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index')->middleware('central.permission:tenants.view');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show')->middleware('central.permission:tenants.view');
        Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store')->middleware('central.permission:tenants.create');
        Route::match(['put', 'patch'], 'tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update')->middleware('central.permission:tenants.update');
        Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy')->middleware('central.permission:tenants.delete');
        Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->middleware('central.permission:tenants.suspend');
        Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate'])->middleware('central.permission:tenants.activate');

        Route::prefix('tenants/{tenant}/domains')->group(function () {
            Route::get('/', [DomainController::class, 'index'])->middleware('central.permission:domains.view');
            Route::post('/', [DomainController::class, 'store'])->middleware('central.permission:domains.create');
            Route::delete('/{domain}', [DomainController::class, 'destroy'])->middleware('central.permission:domains.delete');
        });

        Route::prefix('invoices')->middleware('central.permission:invoices.view')->group(function () {
            Route::get('/', [InvoiceController::class, 'index']);
            Route::get('/{invoice}', [InvoiceController::class, 'show']);
            Route::get('/{invoice}/view', [InvoiceController::class, 'view']);
        });

        Route::get('activity', [ActivityLogController::class, 'index'])->middleware('central.permission:activity.view');

        Route::get('dashboard/summary', [DashboardController::class, 'summary'])->middleware('central.permission:dashboard.view');
    });

});
