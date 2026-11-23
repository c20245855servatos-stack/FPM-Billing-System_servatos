<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoicePaymentRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Requests\UpdateInvoicePaymentRequest;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Services\InvoiceService;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $service) {}

    public function index()
    {
        $user    = request()->user();
        $isAdmin = $user?->isAdmin();

        // For client role, scope to their own client record
        $clientRecord = ! $isAdmin
            ? \App\Models\Client::where('email', $user->email)->first()
            : null;

        $invoiceQuery = Invoice::with(['milestone.project.client', 'payments'])->latest();// SALA  - NEED FURTHER OPTIMIZATION
        if (! $isAdmin && $clientRecord) {
            $invoiceQuery->whereHas('milestone.project', fn ($q) => $q->where('client_id', $clientRecord->id));// SALA  - NEED FURTHER OPTIMIZATION
        } elseif (! $isAdmin) {
            $invoiceQuery->whereRaw('1 = 0'); // no client record — show nothing
        }

        $invoiceCounts = (clone $invoiceQuery)
            ->reorder()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $statusFilter = request()->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'unpaid', 'partial', 'paid'], true)) {
            $statusFilter = 'all';
        }
        if ($statusFilter !== 'all') {
            $invoiceQuery->where('status', $statusFilter);
        }

        $search = trim((string) request()->query('search', ''));
        if ($search !== '') {
            $invoiceQuery->where(function ($query) use ($search) {
                $matchRelated = function ($milestoneQuery) use ($search) {
                    $milestoneQuery->where('title', 'like', "%{$search}%")
                        ->orWhereHas('project', function ($projectQuery) use ($search) {
                            $projectQuery->where('name', 'like', "%{$search}%")
                                ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('company_name', 'like', "%{$search}%"));
                        });
                };

                if (ctype_digit($search)) {
                    $query->where('id', (int) $search)
                        ->orWhereHas('milestone', $matchRelated);
                } else {
                    $query->whereHas('milestone', $matchRelated);
                }
            });
        }

        $invoiceId = request()->query('invoice_id');
        if (ctype_digit((string) $invoiceId)) {
            $invoiceQuery->whereKey((int) $invoiceId);
        }

        $invoices = $invoiceQuery->paginate(15);

        $invoiceRows = $invoices->getCollection()->map(function (Invoice $invoice) {
            $row = $invoice->toArray();
            $row['subtotal'] = (float) $invoice->milestone->amount;
            $row['amount_paid'] = (float) $invoice->amountPaid();
            $row['balance_due'] = (float) $invoice->balanceDue();

            return $row;
        });
        $invoices->setCollection($invoiceRows);

        $availableMilestones = $isAdmin
            ? ProjectMilestone::whereDoesntHave('invoice')
                ->with(['project:id,name,default_tax_rate', 'project.client:id,company_name']) // CORRECT
                ->orderBy('id')
                ->get()
                ->map(fn (ProjectMilestone $milestone) => [
                    'id' => $milestone->id,
                    'title' => $milestone->title,
                    'amount' => (float) $milestone->amount,
                    'project_name' => $milestone->project?->name,
                    'client_name' => $milestone->project?->client?->company_name,
                    'default_tax_rate' => (float) ($milestone->project?->default_tax_rate ?? 0.12),
                ])
            : collect();
        $selectedMilestoneId = null;
        $requestedMilestoneId = request()->query('milestone_id');
        if ($isAdmin && ctype_digit((string) $requestedMilestoneId)) {
            $requestedId = (int) $requestedMilestoneId;
            if ($availableMilestones->contains('id', $requestedId)) {
                $selectedMilestoneId = $requestedId;
            }
        }

        $proposalQuery = Project::with('client')// SALA  - NEED FURTHER OPTIMIZATION
            ->whereIn('approval_status', ['pending', 'countered', 'rejected'])
            ->whereNull('archived_at')
            ->latest();
        if (! $isAdmin && $clientRecord) {
            $proposalQuery->where('client_id', $clientRecord->id);
        } elseif (! $isAdmin) {
            $proposalQuery->whereRaw('1 = 0');
        }

        $proposals = $proposalQuery->get()->map(fn (Project $p) => [
            'id'                   => $p->id,
            'name'                 => $p->name,
            'description'          => $p->description,
            'price'                => (float) $p->price,
            'default_tax_rate'     => (float) $p->default_tax_rate,
            'admin_counter_price'  => $p->admin_counter_price  ? (float) $p->admin_counter_price  : null,
            'client_counter_price' => $p->client_counter_price ? (float) $p->client_counter_price : null,
            'last_counter_by'      => $p->last_counter_by,
            'client'               => $p->client?->company_name,
            'approval_status'      => $p->approval_status,
        ]);

        if (! request()->expectsJson()) {
            return Inertia::render('Invoices', [
                'invoices'  => $invoices,
                'proposals' => $proposals,
                'available_milestones' => $availableMilestones,
                'selected_milestone_id' => $selectedMilestoneId,
                'invoice_pagination' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                    'from' => $invoices->firstItem(),
                    'to' => $invoices->lastItem(),
                ],
                'invoice_counts' => [
                    'all' => array_sum($invoiceCounts),
                    'unpaid' => (int) ($invoiceCounts['unpaid'] ?? 0),
                    'partial' => (int) ($invoiceCounts['partial'] ?? 0),
                    'paid' => (int) ($invoiceCounts['paid'] ?? 0),
                ],
                'filters' => [
                    'status' => $statusFilter,
                    'search' => $search,
                    'invoice_id' => ctype_digit((string) $invoiceId) ? (int) $invoiceId : null,
                ],
            ]);
        }

        return response()->json($invoices);
    }

    public function create()
    {
        return response()->json([
            'milestones' => ProjectMilestone::whereDoesntHave('invoice')
                ->with(['project:id,name,default_tax_rate', 'project.client:id,company_name'])
                ->get(['id', 'project_id', 'title', 'amount'])
                ->map(fn (ProjectMilestone $milestone) => [
                    'id' => $milestone->id,
                    'title' => $milestone->title,
                    'amount' => (float) $milestone->amount,
                    'project_name' => $milestone->project?->name,
                    'client_name' => $milestone->project?->client?->company_name,
                    'default_tax_rate' => (float) ($milestone->project?->default_tax_rate ?? 0.12),
                ]),
        ]);
    }

    public function store(StoreInvoiceRequest $request)
    {
        $invoice = $this->service->createInvoice($request->validated());

        if (! $request->expectsJson()) {
            return redirect()->route('invoices.index')->with('success', 'Invoice created successfully.');
        }

        return response()->json(
            $invoice->load('milestone.project.client'),
            201
        );
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $updated = $this->service->updateInvoice($invoice, $request->validated());

        return response()->json($updated);
    }

    public function storePayment(StoreInvoicePaymentRequest $request, Invoice $invoice)
    {
        $payment = $this->service->recordPayment($invoice, $request->validated());

        if (! $request->expectsJson()) {
            return redirect()->route('invoices.index')->with('success', 'Payment recorded successfully.');
        }

        return response()->json($payment->load('invoice'), 201);
    }

    public function updatePayment(
        UpdateInvoicePaymentRequest $request,
        Invoice $invoice,
        InvoicePayment $payment,
    ) {
        $updated = $this->service->updatePayment(
            $invoice,
            $payment,
            $request->validated(),
        );

        if ($request->expectsJson()) {
            return response()->json($updated->load('invoice'));
        }

        return redirect()->route('invoices.index')->with('success', 'Payment updated successfully.');
    }
}