<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankController;
use App\Http\Controllers\Api\V1\DonationController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::prefix('authentication')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('password/forgot', [AuthController::class, 'forgot']);
        Route::post('password/reset', [AuthController::class, 'reset']);
        Route::get('activate/{token}', [AuthController::class, 'activate']);
        Route::put('activate/manager/{token}', [AuthController::class, 'activateManager']);
        Route::post('resend-activation', [AuthController::class, 'resend']);
    });
    Route::get('states', [AuthController::class, 'states']);
    Route::middleware('jwt')->group(function () {
        Route::put('set-profile', [AuthController::class, 'setProfile']);
        Route::get('get-profile', [AuthController::class, 'profile']);
        Route::get('activate/user/{id}', [AuthController::class, 'activateAdmin'])->middleware('admin');
    });
});
Route::prefix('organization')->group(function () {
    Route::get('list', [OrganizationController::class, 'index']);
    Route::get('public/{id}', [OrganizationController::class, 'show']);
    Route::middleware('jwt')->group(function () {
        Route::get('/', [OrganizationController::class, 'show']);
        Route::get('dashboard', [ReportController::class, 'dashboard']);
        Route::get('overview', [ReportController::class, 'overview']);
        Route::put('update/avatar', [OrganizationController::class, 'avatar'])->middleware('throttle:uploads');
        Route::put('update/password', [OrganizationController::class, 'password']);
        Route::put('update/organization-password', [OrganizationController::class, 'adminPassword'])->middleware('admin');
        Route::post('block/{id}', [OrganizationController::class, 'block'])->middleware('admin');
        Route::put('update', [OrganizationController::class, 'update']);
        Route::delete('delete/{id}', [OrganizationController::class, 'delete'])->middleware('admin');
    });
    Route::get('{id}', [OrganizationController::class, 'show']);
});
Route::prefix('project')->group(function () {
    Route::get('random', [ProjectController::class, 'random']);
    Route::get('organization/{organizationId}', [ProjectController::class, 'organization']);
    Route::middleware('jwt')->group(function () {
        Route::post('create', [ProjectController::class, 'create'])->middleware('throttle:uploads');
        Route::get('all', [ProjectController::class, 'index']);
        Route::get('admin/all', [ProjectController::class, 'admin'])->middleware('admin');
        Route::put('update/{projectId}', [ProjectController::class, 'update']);
        Route::delete('delete/{projectId}', [ProjectController::class, 'delete']);
    });
    Route::get('{projectId}', [ProjectController::class, 'show']);
});
Route::prefix('activity')->group(function () {
    Route::get('public', [ActivityController::class, 'public']);
    Route::get('public/{organizationId}', [ActivityController::class, 'public']);
    Route::middleware('jwt')->group(function () {
        Route::post('add', [ActivityController::class, 'create']);
        Route::get('all', [ActivityController::class, 'index']);
        Route::delete('delete/{activityId}', [ActivityController::class, 'delete']);
        Route::get('{activityId}', [ActivityController::class, 'show']);
        Route::put('{activityId}', [ActivityController::class, 'update']);
    });
});
Route::prefix('bank')->group(function () {
    Route::get('list', [BankController::class, 'banks']);
    Route::middleware('jwt')->group(function () {
        Route::get('/', [BankController::class, 'show']);
        Route::post('verify', [BankController::class, 'verify']);
        Route::post('create', [BankController::class, 'create']);
        Route::put('update/bank-details', [BankController::class, 'update']);
        Route::put('update/{bankId}', [BankController::class, 'update']);
    });
});
Route::put('file/upload', [FileController::class, 'upload'])->middleware(['jwt', 'throttle:uploads']);
Route::get('file/signature', [FileController::class, 'signature'])->middleware(['jwt', 'throttle:uploads']);
Route::prefix('donation')->group(function () {
    Route::post('make-donation', [DonationController::class, 'create'])->middleware('throttle:donations');
    Route::post('webhook', [DonationController::class, 'webhook']);
    Route::middleware('jwt')->group(function () {
        Route::get('/', [DonationController::class, 'index']);
        Route::get('graph/year', [ReportController::class, 'graph']);
        Route::get('project/{projectId}', [DonationController::class, 'project']);
        Route::get('{donationId}', [DonationController::class, 'show']);
    });
});
Route::middleware('jwt')->group(function () {
    Route::get('wallet/balance/admin', [WalletController::class, 'admin'])->middleware('admin');
    Route::get('wallet/{id?}', [WalletController::class, 'show']);
    Route::post('user/add', [UserController::class, 'add']);
    // Compatibility alias; new clients should use DELETE /user/{id}.
    Route::get('user/delete', [UserController::class, 'delete']);
    Route::delete('user/{id}', [UserController::class, 'delete']);
    foreach (['withdraw', 'withdrawal'] as $prefix) {
        Route::prefix($prefix)->group(function () {
            Route::post('/', [WithdrawalController::class, 'create']);
            Route::patch('admin/update/{requestId}', [WithdrawalController::class, 'update'])->middleware('admin');
            Route::get('single/{requestId}', [WithdrawalController::class, 'show']);
            Route::get('admin/all', [WithdrawalController::class, 'admin'])->middleware('admin');
            Route::get('admin/requests', [WithdrawalController::class, 'admin'])->middleware('admin');
            Route::get('all', [WithdrawalController::class, 'index']);
        });
    }
});
Route::get('global/admin/dashboard', [ReportController::class, 'admin'])->middleware(['jwt', 'admin']);
Route::get('global/search', [ReportController::class, 'search']);
