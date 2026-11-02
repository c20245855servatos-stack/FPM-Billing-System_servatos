<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProjectController;

Route::apiResource('projects', ProjectController::class)
    ->only(['index', 'create', 'store', 'update']);

Route::apiResource('invoices', InvoiceController::class)
    ->only(['index', 'create', 'store', 'update']);

Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'storePayment']);