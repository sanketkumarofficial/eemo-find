<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PasswordResetController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SessionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/dashboard');
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AuthController::class, 'create'])->name('login');
        Route::post('login', [AuthController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
        Route::get('forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:admin-reset')->name('password.email');
        Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:admin-reset')->name('password.update');
    });
    Route::middleware(['auth:admin', 'auth.session'])->group(function () {
        Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('profile', [ProfileController::class, 'index'])->name('profile');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/photo', [ProfileController::class, 'photo'])->name('profile.photo.update');
        Route::delete('profile/photo', [ProfileController::class, 'removePhoto'])->name('profile.photo.delete');
        Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password.update');
        Route::put('profile/logo', [ProfileController::class, 'logo'])->middleware('can:manage-branding')->name('profile.logo.update');
        Route::delete('profile/logo', [ProfileController::class, 'removeLogo'])->middleware('can:manage-branding')->name('profile.logo.delete');
        Route::get('security/activity-logs', [ActivityLogController::class, 'index'])->name('security.activity-logs.index');
        Route::get('security/activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('security.activity-logs.show');
        Route::get('security/active-sessions', [SessionController::class, 'index'])->name('security.active-sessions.index');
        // Opaque encrypted references are submitted in the body, never raw session IDs.
        Route::delete('security/active-sessions', [SessionController::class, 'destroy'])->name('security.active-sessions.destroy');
    });
});
