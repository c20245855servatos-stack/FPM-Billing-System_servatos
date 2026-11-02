<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\DomainService;

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
});

// Show the form
Route::get('/create-project', function () {
    return Inertia::render('CreateProject', [
        'clients' => Client::all() 
    ]);
});

// Handle the form submission
Route::post('/projects', function (\Illuminate\Http\Request $request) {
    // 1. Validate the data (Notice the unique rule!)
    $data = $request->validate([
        'name' => 'required|min:2|unique:projects,name', 
        'client_id' => 'required|exists:clients,id'
    ], [
        // Custom error message
        'name.unique' => 'This project name already exists in the system.',
        'name.required' => 'Please enter a project name.'
    ]);

    // 2. Send to Service Layer
    $service = new DomainService();
    $service->createProject($data);

    // 3. Redirect back
    return redirect('/create-project');
});

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';