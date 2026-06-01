<?php

use App\Http\Controllers\Admin\LeaveTenantController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/admin/tenancy/leave', LeaveTenantController::class)
        ->name('admin.tenancy.leave');
});
