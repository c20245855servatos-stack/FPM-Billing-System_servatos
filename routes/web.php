<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectMilestoneController;
use App\Http\Controllers\InvoiceController;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// ─── Shared authenticated routes (all roles) ────────────────────────────────
Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        $user    = request()->user();
        $isAdmin = $user?->isAdmin();

        if ($isAdmin) {
            // ── Admin: business overview ──────────────────────────────────
            $invoices = Invoice::with(['milestone.project.client'])
                ->latest()->limit(6)->get();

            // Paid revenue follows actual recorded payments, including partial payments.
            $paidFromInvoices = (float) InvoicePayment::sum('amount_paid');

            $paidFromNoInvoice = ProjectMilestone::where('status', 'paid')
                ->whereDoesntHave('invoice')
                ->with('project')
                ->get()
                ->sum(function ($milestone) {
                    $taxRate = (float) ($milestone->project?->default_tax_rate ?? 0.12);
                    $amount  = (float) $milestone->amount;
                    return round($amount + $amount * $taxRate, 2);
                });

            return Inertia::render('dashboard', [
                'role'    => 'admin',
                'summary' => [
                    'clients'      => Client::count(),
                    'projects'     => Project::where('approval_status', 'accepted')->active()->count(),
                    'unpaid'       => Invoice::whereIn('status', ['unpaid', 'partial'])->count(),
                    'paid_amount'  => round($paidFromInvoices + $paidFromNoInvoice, 2),
                ],
                'recentInvoices' => $invoices->map(fn (Invoice $invoice) => [
                    'id'        => $invoice->id,
                    'status'    => $invoice->status,
                    'total'     => (float) $invoice->total_amount_due,
                    'client'    => $invoice->milestone?->project?->client?->company_name,
                    'project'   => $invoice->milestone?->project?->name,
                    'milestone' => $invoice->milestone?->title,
                ]),
                'recentProjects' => Project::with('client')
                    ->withCount('milestones')
                    ->where('approval_status', 'accepted')
                    ->active()
                    ->latest()->limit(5)->get()
                    ->map(fn (Project $project) => [
                        'id'               => $project->id,
                        'name'             => $project->name,
                        'client'           => $project->client?->company_name,
                        'milestones_count' => $project->milestones_count,
                    ]),
            ]);
        }

        // ── Client: personal billing overview ────────────────────────────
        $client = Client::where('email', $user->email)->first();

        $projects = $client
            ? Project::where('client_id', $client->id)
                ->with(['milestones.invoice.payments'])
                ->where('approval_status', 'accepted')
                ->active()->get()
            : collect();

        $proposals = $client
            ? Project::where('client_id', $client->id)
                ->whereIn('approval_status', ['pending', 'countered'])
                ->count()
            : 0;

        $totalDue = 0;
        $totalPaid = 0;
        foreach ($projects as $project) {
            $taxRate = (float) $project->default_tax_rate;
            foreach ($project->milestones as $milestone) {
                $invoice = $milestone->invoice;
                if ($invoice) {
                    $totalDue  += (float) $invoice->total_amount_due;
                    // Use actual payments if they exist, otherwise fall back
                    // to invoice total_amount_due when status is paid
                    $amountPaid = (float) $invoice->payments->sum('amount_paid');
                    if ($amountPaid == 0 && $invoice->status === 'paid') {
                        $amountPaid = (float) $invoice->total_amount_due;
                    }
                    $totalPaid += $amountPaid;
                } else {
                    $amt = (float) $milestone->amount;
                    $milestoneTotal = round($amt + $amt * $taxRate, 2);
                    $totalDue += $milestoneTotal;
                    // No invoice but milestone manually marked paid
                    if ($milestone->status === 'paid') {
                        $totalPaid += $milestoneTotal;
                    }
                }
            }
        }

        $recentInvoices = Invoice::with(['milestone.project'])
            ->whereHas('milestone.project', fn ($q) => $q->where('client_id', $client?->id ?? 0))
            ->latest()->limit(5)->get()
            ->map(fn (Invoice $invoice) => [
                'id'        => $invoice->id,
                'status'    => $invoice->status,
                'total'     => (float) $invoice->total_amount_due,
                'project'   => $invoice->milestone?->project?->name,
                'milestone' => $invoice->milestone?->title,
            ]);

        return Inertia::render('dashboard', [
            'role'    => 'user',
            'summary' => [
                'projects'   => $projects->count(),
                'proposals'  => $proposals,
                'total_due'  => round($totalDue, 2),
                'total_paid' => round($totalPaid, 2),
                'balance'    => round($totalDue - $totalPaid, 2),
            ],
            'recentInvoices' => $recentInvoices,
            'recentProjects' => $projects->take(5)->map(fn (Project $project) => [
                'id'               => $project->id,
                'name'             => $project->name,
                'milestones_count' => $project->milestones->count(),
                'approval_status'  => $project->approval_status,
            ]),
        ]);
    })->name('dashboard');

    // Invoices page — accessible to both admin and client
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
});

// ─── Admin-only routes (Freelancer) ─────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::match(['put', 'patch'], '/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::patch('/projects/{project}/approval', [ProjectController::class, 'updateApproval'])->name('projects.approval.update');
    Route::patch('/projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::patch('/projects/{project}/unarchive', [ProjectController::class, 'unarchive'])->name('projects.unarchive');
    Route::patch('/projects/{project}/counter', [ProjectController::class, 'counter'])->name('projects.counter');
    Route::patch('/projects/{project}/agree-client-counter', [ProjectController::class, 'agreeClientCounter'])->name('projects.agree.client.counter');

    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::resource('clients', ClientController::class)->except(['store'])->only(['index', 'create', 'update']);

    Route::resource('milestones', ProjectMilestoneController::class)->except(['show', 'destroy']);
    Route::patch('/milestones/{milestone}/status', [ProjectMilestoneController::class, 'updateStatus'])->name('milestones.status.update');

    Route::resource('invoices', InvoiceController::class)->only(['create', 'store', 'update']);
    Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])->name('invoices.payments.store');
    Route::patch('/invoices/{invoice}/payments/{payment}', [InvoiceController::class, 'updatePayment'])->name('invoices.payments.update');
});

// ─── Client-only routes (Client company) ────────────────────────────────────
Route::middleware(['auth', 'role:user'])->group(function () {
    Route::get('/create-project', [ProjectController::class, 'createInertia'])->name('projects.create');
    Route::get('/my-projects', [ProjectController::class, 'mine'])->name('projects.mine');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::patch('/my-projects/{project}', [ProjectController::class, 'updateClient'])->name('projects.mine.update');
    Route::patch('/my-projects/{project}/agree-counter', [ProjectController::class, 'agreeCounter'])->name('projects.agree.counter');
    Route::patch('/my-projects/{project}/client-counter', [ProjectController::class, 'clientCounter'])->name('projects.client.counter');
    Route::patch('/my-projects/{project}/reject', [ProjectController::class, 'rejectByClient'])->name('projects.client.reject');
});

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
