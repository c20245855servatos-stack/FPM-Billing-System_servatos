<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectMilestoneController;
use App\Http\Controllers\InvoiceController;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        $invoices = Invoice::with(['milestone.project.client'])
            ->latest()
            ->limit(6)
            ->get();

        return Inertia::render('dashboard', [
            'summary' => [
                'clients' => Client::count(),
                'projects' => Project::count(),
                'unpaid' => Invoice::whereIn('status', ['unpaid', 'partial'])->count(),
                'paid_amount' => (float) Invoice::where('status', 'paid')->sum('total_amount_due'),
            ],
            'recentInvoices' => $invoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'status' => $invoice->status,
                'total' => (float) $invoice->total_amount_due,
                'client' => $invoice->milestone?->project?->client?->company_name,
                'project' => $invoice->milestone?->project?->name,
                'milestone' => $invoice->milestone?->title,
            ]),
            'recentProjects' => Project::with('client')
                ->withCount('milestones')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client' => $project->client?->company_name,
                    'milestones_count' => $project->milestones_count,
                ]),
        ]);
    })->name('dashboard');
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/create-project', [ProjectController::class, 'createInertia'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::match(['put', 'patch'], '/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::resource('clients', ClientController::class)->except(['store'])->only(['index', 'create', 'update']);
    Route::resource('milestones', ProjectMilestoneController::class)->except(['show', 'destroy']);
    Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'update']);
    Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])->name('invoices.payments.store');
});

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';