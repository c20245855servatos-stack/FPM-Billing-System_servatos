<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectMilestoneController;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::apiResource('projects', ProjectController::class)
        ->only(['index', 'update'])
        ->names([
            'index'  => 'api.projects.index',
            'update' => 'api.projects.update',
        ]);

    Route::apiResource('invoices', InvoiceController::class)
        ->only(['index', 'create', 'store', 'update'])
        ->names([
            'index'  => 'api.invoices.index',
            'create' => 'api.invoices.create',
            'store'  => 'api.invoices.store',
            'update' => 'api.invoices.update',
        ]);

    Route::apiResource('clients', ClientController::class)
        ->only(['index', 'create', 'store', 'update'])
        ->names([
            'index'  => 'api.clients.index',
            'create' => 'api.clients.create',
            'store'  => 'api.clients.store',
            'update' => 'api.clients.update',
        ]);

    Route::apiResource('milestones', ProjectMilestoneController::class)
        ->only(['index', 'create', 'store', 'update'])
        ->names([
            'index'  => 'api.milestones.index',
            'create' => 'api.milestones.create',
            'store'  => 'api.milestones.store',
            'update' => 'api.milestones.update',
        ]);

    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])
        ->name('api.invoices.payments.store');
    Route::patch('invoices/{invoice}/payments/{payment}', [InvoiceController::class, 'updatePayment'])
        ->name('api.invoices.payments.update');
});

Route::middleware(['auth', 'role:user'])->group(function () {
    Route::apiResource('projects', ProjectController::class)
        ->only(['create', 'store'])
        ->names([
            'create' => 'api.projects.create',
            'store'  => 'api.projects.store',
        ]);
});
