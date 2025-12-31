<?php

use App\Http\Controllers\CopilotController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Copilot routes
    Route::get('copilot', [CopilotController::class, 'index'])->name('copilot.index');
    Route::get('copilot/{threadId}', [CopilotController::class, 'show'])->name('copilot.show');
    Route::post('copilot/send', [CopilotController::class, 'send'])->name('copilot.send');
    Route::delete('copilot/{threadId}', [CopilotController::class, 'destroy'])->name('copilot.destroy');
});

require __DIR__.'/settings.php';
