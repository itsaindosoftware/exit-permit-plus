<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExitPermitApprovalController;
use App\Http\Controllers\Api\ReimbursementApprovalController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [AttendanceController::class, 'dashboard']);
    Route::post('/devices/fcm-token', [AuthController::class, 'updateFcmToken']);
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
    Route::post('/exit-permits/{exitPermit}/approval', [ExitPermitApprovalController::class, 'submit']);
    Route::post('/reimbursements/{reimbursement}/approval', [ReimbursementApprovalController::class, 'submit']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
