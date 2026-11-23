<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Services\ClientService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function __construct(private ClientService $service) {}

    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(
                Client::withCount('projects')->latest()->paginate(15)
            );
        }

        return Inertia::render('Clients', [
            'clients' => Client::with([
                    'projects.milestones.invoice.payments', // SALA  - NEED FURTHER OPTIMIZATION
                ])
                ->withCount('projects')
                ->orderBy('company_name')
                ->get()
                ->map(fn (Client $client) => [
                    'id'             => $client->id,
                    'company_name'   => $client->company_name,
                    'email'          => $client->email,
                    'projects_count' => $client->projects_count,
                    'billing'        => $this->clientBilling($client),
                ]),
        ]);
    }

    public function create()
    {
        return response()->json(['fields' => ['company_name', 'email']]);
    }

    public function store(StoreClientRequest $request)
    {
        $client = $this->service->createClient($request->validated());

        if (! $request->expectsJson()) {
            return redirect()->back()->with('success', "Client {$client->company_name} created successfully.");
        }

        return response()->json($client, 201);
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $updated = $this->service->updateClient($client, $request->validated());

        return response()->json($updated);
    }

    private function clientBilling(Client $client): array
    {
        $subtotal = 0.0;
        $tax      = 0.0;
        $totalDue = 0.0;
        $paid     = 0.0;

        foreach ($client->projects as $project) {
            $taxRate = (float) $project->default_tax_rate;

            foreach ($project->milestones as $milestone) {
                $amount  = (float) $milestone->amount;
                $invoice = $milestone->invoice;

                if ($invoice) {
                    $invoiceTax   = (float) $invoice->tax_amount;
                    $invoiceTotal = (float) $invoice->total_amount_due;
                    $amountPaid   = (float) $invoice->payments->sum('amount_paid');
                } else {
                    // Estimate using project default tax rate
                    $invoiceTax   = round($amount * $taxRate, 2);
                    $invoiceTotal = round($amount + $invoiceTax, 2);
                    $amountPaid   = 0.0;
                }

                $subtotal += $amount;
                $tax      += $invoiceTax;
                $totalDue += $invoiceTotal;
                $paid     += $amountPaid;
            }
        }

        return [
            'subtotal'  => round($subtotal, 2),
            'tax'       => round($tax, 2),
            'total_due' => round($totalDue, 2),
            'paid'      => round($paid, 2),
            'balance'   => round($totalDue - $paid, 2),
        ];
    }
}
