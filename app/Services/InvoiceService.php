<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\ProjectMilestone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
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
            $taxAmount = round($subtotal * $taxRate, 2);
            $totalDue = round($subtotal + $taxAmount, 2);

            $invoice = Invoice::create([
                'milestone_id' => $milestone->id,
                'tax_rate' => $taxRate,
                'tax_amount' => $this->formatCurrency($taxAmount),
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
                $taxAmount = round($subtotal * $taxRate, 2);
                $totalDue = round($subtotal + $taxAmount, 2);

                $payload['tax_rate'] = $taxRate;
                $payload['tax_amount'] = $this->formatCurrency($taxAmount);
                $payload['total_amount_due'] = $this->formatCurrency($totalDue);
            }

            if (isset($data['status'])) {
                $payload['status'] = $data['status'];
            }

            $invoice->update($payload);

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
                'paid_at' => filled($data['paid_at'] ?? null) ? $data['paid_at'] : now(),
                'method' => filled($data['method'] ?? null) ? $data['method'] : 'bank_transfer',
            ]);

            $paid = $invoice->amountPaid();
            $due = (float) $invoice->total_amount_due;

            if ($paid >= $due) {
                $invoice->update(['status' => 'paid']);
                $invoice->milestone->update(['status' => 'paid']);
            } elseif ($paid > 0) {
                $invoice->update(['status' => 'partial']);
            }

            return $payment;
        });
    }

    private function formatCurrency(float $value): float
    {
        return round($value, 2);
    }
}
