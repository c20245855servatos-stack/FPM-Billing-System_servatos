<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Client;
use App\Models\InvoicePayment;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class DomainService
{
    public function createClient(array $data): Client
    {
        return DB::transaction(function () use ($data) {
            return Client::create([
                'company_name' => trim(strip_tags($data['company_name'])),
                'email' => $this->sanitizeEmail($data['email']),
            ]);
        });
    }

    public function updateClient(Client $client, array $data): Client
    {
        return DB::transaction(function () use ($data, $client) {
            if (isset($data['company_name'])) {
                $client->company_name = trim(strip_tags($data['company_name']));
            }
            if (isset($data['email'])) {
                $client->email = $this->sanitizeEmail($data['email']);
            }
            $client->save();

            return $client->fresh();
        });
    }

    /* ============================================================
     *  PROJECT OPERATIONS
     * ============================================================ */

    public function createProject(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            return Project::create($this->sanitizeProject($data));
        });
    }

    public function updateProject(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data) {
            $project->update($this->sanitizeProject($data));

            return $project->fresh();
        });
    }

    private function sanitizeProject(array $data): array
    {
        $clean = [];
        if (isset($data['client_id'])) {
            $clean['client_id'] = (int) $data['client_id'];
        }
        if (isset($data['name'])) {
            $clean['name'] = trim(strip_tags($data['name']));
        }

        return $clean;
    }

    /* ============================================================
     *  INVOICE OPERATIONS — BUSINESS RULES
     * ============================================================ */

    public function createInvoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $milestone = ProjectMilestone::findOrFail($data['milestone_id']);

            if ($milestone->invoice()->exists()) {
                throw ValidationException::withMessages([
                    'milestone_id' => ['This milestone has already been invoiced.'],
                ]);
            }

            $taxRate = (float) $data['tax_rate'];
            $subtotal = (float) $milestone->amount;
            $taxAmt = round($subtotal * $taxRate, 2);
            $totalDue = round($subtotal + $taxAmt, 2);

            $invoice = Invoice::create([
                'milestone_id' => $milestone->id,
                'tax_rate' => $taxRate,
                'tax_amount' => $this->formatCurrency($taxAmt),
                'total_amount_due' => $this->formatCurrency($totalDue),
                'status' => 'unpaid',
            ]);

            $milestone->update(['status' => 'invoiced']);

            return $invoice;
        });
    }

    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $payload = [];

            if (isset($data['tax_rate'])) {
                $taxRate = (float) $data['tax_rate'];
                $subtotal = (float) $invoice->milestone->amount;
                $taxAmt = round($subtotal * $taxRate, 2);
                $totalDue = round($subtotal + $taxAmt, 2);

                $payload['tax_rate'] = $taxRate;
                $payload['tax_amount'] = $this->formatCurrency($taxAmt);
                $payload['total_amount_due'] = $this->formatCurrency($totalDue);
            }

            if (isset($data['status'])) {
                $payload['status'] = $data['status'];
            }

            $invoice->update($payload);

            // Business rule: update milestone status when invoice is paid
            if (($payload['status'] ?? null) === 'paid') {
                $invoice->milestone->update(['status' => 'paid']);
            }

            return $invoice->fresh(['milestone', 'payments']);
        });
    }

    public function recordPayment(Invoice $invoice, array $data): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->id);
            $amount = round((float) $data['amount_paid'], 2);
            $balance = (float) $invoice->balanceDue();

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount_paid' => ['Payment amount must be greater than zero.'],
                ]);
            }
            if ($amount > $balance + 0.001) {
                throw ValidationException::withMessages([
                    'amount_paid' => ['Payment amount cannot exceed the invoice balance.'],
                ]);
            }

            $payment = InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'amount_paid' => $this->formatCurrency($amount),
                'paid_at' => $data['paid_at'] ?? now(),
                'method' => $data['method'] ?? 'bank_transfer',
            ]);

            $paid = $invoice->amountPaid();
            $due = (float) $invoice->total_amount_due;

            if ($paid >= $due) {
                $invoice->update(['status' => 'paid']);
                $invoice->milestone->update(['status' => 'paid']);   // business rule
            } elseif ($paid > 0) {
                $invoice->update(['status' => 'partial']);
            }

            return $payment;
        });
    }

    /* ============================================================
     *  SANITIZATION HELPERS
     * ============================================================ */

    public function sanitizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    public function formatCurrency(float $value): float
    {
        return round($value, 2);
    }
}
