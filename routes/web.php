<?php

use App\Http\Controllers\Auth\TenantLoginController;
use App\Http\Controllers\Admin\LeaveTenantController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::get('/login', [TenantLoginController::class, 'create'])->name('login');
Route::post('/login', [TenantLoginController::class, 'store'])->name('login.store');
Route::get('/tenant/login', [TenantLoginController::class, 'create'])->name('tenant.login');
Route::post('/tenant/login', [TenantLoginController::class, 'store'])->name('tenant.login.store');

Route::middleware('auth')->group(function (): void {
    Route::get('/admin/tenancy/leave', LeaveTenantController::class)
        ->name('admin.tenancy.leave');
});
