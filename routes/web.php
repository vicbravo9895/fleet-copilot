<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CopilotController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\CompanyController as SuperAdminCompanyController;
use App\Http\Controllers\SuperAdmin\UserController as SuperAdminUserController;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Redirigir raíz: si autenticado → dashboard, si no → login
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Copilot routes
    Route::get('copilot', [CopilotController::class, 'index'])->name('copilot.index');
    Route::get('copilot/{threadId}', [CopilotController::class, 'show'])->name('copilot.show');
    Route::post('copilot/send', [CopilotController::class, 'send'])->name('copilot.send');
    Route::delete('copilot/{threadId}', [CopilotController::class, 'destroy'])->name('copilot.destroy');

    // User management routes (admin/manager only)
    Route::resource('users', UserController::class)->except(['show']);

    // Company settings routes (admin only)
    Route::get('company', [CompanyController::class, 'edit'])->name('company.edit');
    Route::put('company', [CompanyController::class, 'update'])->name('company.update');
    Route::put('company/samsara-key', [CompanyController::class, 'updateSamsaraKey'])->name('company.samsara-key.update');
    Route::delete('company/samsara-key', [CompanyController::class, 'removeSamsaraKey'])->name('company.samsara-key.destroy');
});

// Super Admin routes
Route::middleware(['auth', 'verified', EnsureSuperAdmin::class])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        // Dashboard
        Route::get('/', [SuperAdminDashboardController::class, 'index'])->name('dashboard');
        
        // Companies management
        Route::resource('companies', SuperAdminCompanyController::class);
        Route::put('companies/{company}/samsara-key', [SuperAdminCompanyController::class, 'updateSamsaraKey'])
            ->name('companies.samsara-key.update');
        Route::delete('companies/{company}/samsara-key', [SuperAdminCompanyController::class, 'removeSamsaraKey'])
            ->name('companies.samsara-key.destroy');
        Route::post('companies/{company}/toggle-status', [SuperAdminCompanyController::class, 'toggleStatus'])
            ->name('companies.toggle-status');
        
        // Users management
        Route::resource('users', SuperAdminUserController::class);
        Route::post('users/{user}/toggle-status', [SuperAdminUserController::class, 'toggleStatus'])
            ->name('users.toggle-status');
    });

require __DIR__.'/settings.php';
