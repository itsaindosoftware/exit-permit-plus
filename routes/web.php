<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExitPermitController;
use App\Http\Controllers\OrderMealController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReimbursementController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canLogin' => Route::has('login'),
//         'canRegister' => Route::has('register'),
//         'laravelVersion' => Application::VERSION,
//         'phpVersion' => PHP_VERSION,
//     ]);
// });

Route::get('/', DashboardController::class)->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('exit-permits/{exitPermit}/attachment', [ExitPermitController::class, 'attachment'])
        ->name('exit-permits.attachment');
    Route::resource('exit-permits', ExitPermitController::class);
    Route::resource('order-meals', OrderMealController::class)->except(['show']);
    Route::get('exit-permit-meals', [OrderMealController::class, 'indexExitPermit'])->name('exit-permit-meals.index');
    Route::get('exit-permit-meals/create', [OrderMealController::class, 'createExitPermit'])->name('exit-permit-meals.create');
    Route::post('exit-permit-meals', [OrderMealController::class, 'storeExitPermit'])->name('exit-permit-meals.store');
    Route::get('exit-permit-meals/{orderMeal}/edit', [OrderMealController::class, 'editExitPermit'])->name('exit-permit-meals.edit');
    Route::put('exit-permit-meals/{orderMeal}', [OrderMealController::class, 'updateExitPermit'])->name('exit-permit-meals.update');
    Route::delete('exit-permit-meals/{orderMeal}', [OrderMealController::class, 'destroyExitPermit'])->name('exit-permit-meals.destroy');
    Route::resource('reimbursements', ReimbursementController::class)->except(['show', 'destroy']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
