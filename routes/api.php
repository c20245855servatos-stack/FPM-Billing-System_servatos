<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectMilestoneController;

Route::middleware('auth')->group(function () {
    Route::apiResource('projects', ProjectController::class)
        ->only(['index', 'create', 'store', 'update']);

    Route::apiResource('invoices', InvoiceController::class)
        ->only(['index', 'create', 'store', 'update']);

    Route::apiResource('clients', ClientController::class)
        ->only(['index', 'create', 'store', 'update']);

    Route::apiResource('milestones', ProjectMilestoneController::class)
        ->only(['index', 'create', 'store', 'update']);

    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'storePayment']);
});