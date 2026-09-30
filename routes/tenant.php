<?php

use App\Http\Controllers\Tenant\ActivityLogController;
use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\CompanySettingController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\OrderController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\ProfileController;
use App\Http\Controllers\Tenant\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/tenant')->middleware(['api', 'tenant'])->group(function () {

    Route::middleware('throttle:auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('tenant.verification.verify');

    Route::post('/accept-invite', [UserManagementController::class, 'acceptInvite']);

    Route::middleware('auth:tenant')->group(function () {

        Route::prefix('company-settings')->group(function () {
            Route::get('/', [CompanySettingController::class, 'show']);
            Route::put('/', [CompanySettingController::class, 'update']);
        });

        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show']);
            Route::put('/', [ProfileController::class, 'update']);
        });

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:6,1');

        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
            Route::post('/{notification}/read', [NotificationController::class, 'markAsRead']);
        });

        Route::prefix('customers')->group(function () {
            Route::get('/', [CustomerController::class, 'index'])->middleware('tenant.permission:customers.view');
            Route::post('/', [CustomerController::class, 'store'])->middleware('tenant.permission:customers.create');
            Route::get('/{customer}', [CustomerController::class, 'show'])->middleware('tenant.permission:customers.view');
            Route::match(['put', 'patch'], '/{customer}', [CustomerController::class, 'update'])->middleware('tenant.permission:customers.update');
            Route::delete('/{customer}', [CustomerController::class, 'destroy'])->middleware('tenant.permission:customers.delete');
        });

        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->middleware('tenant.permission:products.view');
            Route::post('/', [ProductController::class, 'store'])->middleware('tenant.permission:products.create');
            Route::get('/{product}', [ProductController::class, 'show'])->middleware('tenant.permission:products.view');
            Route::match(['put', 'patch'], '/{product}', [ProductController::class, 'update'])->middleware('tenant.permission:products.update');
            Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('tenant.permission:products.delete');
        });

        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->middleware('tenant.permission:orders.view');
            Route::post('/', [OrderController::class, 'store'])->middleware('tenant.permission:orders.create');
            Route::get('/{order}', [OrderController::class, 'show'])->middleware('tenant.permission:orders.view');
            Route::match(['put', 'patch'], '/{order}', [OrderController::class, 'update'])->middleware('tenant.permission:orders.update');
            Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->middleware('tenant.permission:orders.cancel');
        });

        Route::prefix('users')->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])->middleware('tenant.permission:users.view');
            Route::post('/invite', [UserManagementController::class, 'invite'])->middleware('tenant.permission:users.invite');
            Route::post('/{user}/role', [UserManagementController::class, 'assignRole'])->middleware('tenant.permission:users.assign-role');
            Route::post('/{user}/deactivate', [UserManagementController::class, 'deactivate'])->middleware('tenant.permission:users.deactivate');
        });

        Route::get('activity', [ActivityLogController::class, 'index'])->middleware('tenant.permission:activity.view');
    });
});
