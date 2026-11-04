<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoicePaymentRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Models\ProjectMilestone;
use App\Services\InvoiceService;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $service) {}

    // Eager-load milestone, its project, the client, and payments
    public function index()
    {
        $invoices = Invoice::with(['milestone.project.client', 'payments'])
            ->latest()
            ->paginate(15);

        return response()->json($invoices);
    }

    public function create()
    {
        return response()->json([
            'milestones' => ProjectMilestone::whereDoesntHave('invoice')
                ->with('project:id,name')
                ->get(['id', 'project_id', 'title', 'amount']),
        ]);
    }

    public function store(StoreInvoiceRequest $request)
    {
        $invoice = $this->service->createInvoice($request->validated());

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

        return response()->json($payment->load('invoice'), 201);
    }
}