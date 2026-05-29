<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExitPermitListController;
use App\Http\Controllers\ExitPermitController;
use App\Http\Controllers\OrderMealController;
use App\Http\Controllers\PriceSupplierController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReimbursementController;
use App\Http\Controllers\ScheduleCarController;
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
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::get('exit-permit-list', ExitPermitListController::class)->name('exit-permit-list.index');
    Route::get('exit-permit-approvals', [ExitPermitController::class, 'approvalIndex'])->name('exit-permit-approvals.index');
    Route::get('exit-permit-history', [ExitPermitController::class, 'historyIndex'])->name('exit-permit-history.index');
    Route::get('exit-permits/requestor-options', [ExitPermitController::class, 'requestorLookup'])
        ->name('exit-permits.requestor-options');
    Route::get('schedule-cars', [ScheduleCarController::class, 'index'])->name('schedule-cars.index');
    Route::get('schedule-cars/create', [ScheduleCarController::class, 'create'])->name('schedule-cars.create');
    Route::post('schedule-cars', [ScheduleCarController::class, 'store'])->name('schedule-cars.store');
    Route::get('schedule-cars/{exitPermit}/edit', [ScheduleCarController::class, 'edit'])->name('schedule-cars.edit');
    Route::put('schedule-cars/{exitPermit}', [ScheduleCarController::class, 'update'])->name('schedule-cars.update');
    Route::get('exit-permits/{exitPermit}/attachment', [ExitPermitController::class, 'attachment'])
        ->name('exit-permits.attachment');
    Route::get('exit-permits/{exitPermit}/print', [ExitPermitController::class, 'print'])
        ->name('exit-permits.print');
    /*
    Route::post('exit-permits/{exitPermit}/attendance-preview', [ExitPermitController::class, 'previewAttendance'])
        ->name('exit-permits.attendance-preview');
    */
    Route::get('reimbursements/{reimbursement}/attachment', [ReimbursementController::class, 'attachment'])
        ->name('reimbursements.attachment');
    Route::get('reimbursements/{reimbursement}/print', [ReimbursementController::class, 'print'])
        ->name('reimbursements.print');
    Route::get('reimbursement-documents/{document}/attachment', [ReimbursementController::class, 'documentAttachment'])
        ->name('reimbursement-documents.attachment');
    Route::get('reimbursement-approvals', [ReimbursementController::class, 'approvalIndex'])
        ->name('reimbursement-approvals.index');
    Route::get('reimbursement-history', [ReimbursementController::class, 'historyIndex'])
        ->name('reimbursement-history.index');
    Route::get('reimbursements/{reimbursement}', [ReimbursementController::class, 'show'])
        ->whereNumber('reimbursement')
        ->name('reimbursements.show');
    Route::resource('exit-permits', ExitPermitController::class);
    Route::resource('price-suppliers', PriceSupplierController::class);
    Route::get('order-meals/print', [OrderMealController::class, 'print'])->name('order-meals.print');
    Route::get('order-meals/{orderMeal}/print', [OrderMealController::class, 'printItem'])->name('order-meals.print-item');
    Route::resource('order-meals', OrderMealController::class)->except([]);
    Route::get('exit-permit-meals', [OrderMealController::class, 'indexExitPermit'])->name('exit-permit-meals.index');
    Route::get('exit-permit-meals/print', [OrderMealController::class, 'printExitPermit'])->name('exit-permit-meals.print');
    Route::get('exit-permit-meals/{orderMeal}/print', [OrderMealController::class, 'printExitPermitItem'])->name('exit-permit-meals.print-item');
    Route::get('exit-permit-meals/create', [OrderMealController::class, 'createExitPermit'])->name('exit-permit-meals.create');
    Route::post('exit-permit-meals', [OrderMealController::class, 'storeExitPermit'])->name('exit-permit-meals.store');
    Route::get('exit-permit-meals/{orderMeal}', [OrderMealController::class, 'showExitPermit'])->name('exit-permit-meals.show');
    Route::get('exit-permit-meals/{orderMeal}/edit', [OrderMealController::class, 'editExitPermit'])->name('exit-permit-meals.edit');
    Route::put('exit-permit-meals/{orderMeal}', [OrderMealController::class, 'updateExitPermit'])->name('exit-permit-meals.update');
    Route::delete('exit-permit-meals/{orderMeal}', [OrderMealController::class, 'destroyExitPermit'])->name('exit-permit-meals.destroy');
    Route::resource('reimbursements', ReimbursementController::class)->except(['show', 'destroy']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
