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
                $this->assertValidStatusTransition($invoice, $data['status']);
                $payload['status'] = $data['status'];
            }

            $invoice->update($payload);
            $this->syncMilestoneStatus($invoice);

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

            $invoice->refresh();
            $invoice->update(['status' => $this->resolveInvoiceStatus($invoice)]);
            $this->syncMilestoneStatus($invoice);

            return $payment->fresh();
        });
    }

    public function updatePayment(
        Invoice $invoice,
        InvoicePayment $payment,
        array $data,
    ): InvoicePayment {
        return DB::transaction(function () use ($invoice, $payment, $data) {
            if ((int) $payment->invoice_id !== (int) $invoice->id) {
                throw ValidationException::withMessages([
                    'payment' => ['This payment does not belong to the selected invoice.'],
                ]);
            }

            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $payment = InvoicePayment::query()->lockForUpdate()->findOrFail($payment->id);

            $payload = [];
            if (isset($data['amount_paid'])) {
                $amount = round((float) $data['amount_paid'], 2);
                $adjustedBalance = (float) $invoice->balanceDue()
                    + (float) $payment->amount_paid;

                if ($amount <= 0) {
                    throw ValidationException::withMessages([
                        'amount_paid' => ['Payment amount must be greater than zero.'],
                    ]);
                }
                if ($amount > $adjustedBalance + 0.001) {
                    throw ValidationException::withMessages([
                        'amount_paid' => ['Payment amount cannot exceed the invoice balance.'],
                    ]);
                }
                $payload['amount_paid'] = $this->formatCurrency($amount);
            }
            if (array_key_exists('paid_at', $data)) {
                $payload['paid_at'] = filled($data['paid_at']) ? $data['paid_at'] : now();
            }
            if (isset($data['method'])) {
                $payload['method'] = $data['method'];
            }

            $payment->update($payload);
            $invoice->refresh();
            $invoice->update(['status' => $this->resolveInvoiceStatus($invoice)]);
            $this->syncMilestoneStatus($invoice);

            return $payment->fresh();
        });
    }

    private function assertValidStatusTransition(Invoice $invoice, string $status): void
    {
        $amountPaid = (float) $invoice->amountPaid();
        $totalDue = (float) $invoice->total_amount_due;

        if ($status === 'paid' && $amountPaid + 0.001 < $totalDue) {
            throw ValidationException::withMessages([
                'status' => ['Invoice cannot be marked paid until the full balance is paid.'],
            ]);
        }

        if ($status === 'partial' && $amountPaid <= 0) {
            throw ValidationException::withMessages([
                'status' => ['Invoice can only be marked partial when a payment has been received.'],
            ]);
        }
    }

    private function resolveInvoiceStatus(Invoice $invoice): string
    {
        $amountPaid = (float) $invoice->amountPaid();
        $totalDue = (float) $invoice->total_amount_due;

        if ($amountPaid >= $totalDue) {
            return 'paid';
        }

        if ($amountPaid > 0) {
            return 'partial';
        }

        return 'unpaid';
    }

    private function syncMilestoneStatus(Invoice $invoice): void
    {
        if (! $invoice->relationLoaded('milestone') && ! $invoice->milestone()->exists()) {
            return;
        }

        $status = (float) $invoice->amountPaid() >= (float) $invoice->total_amount_due
            ? 'paid'
            : 'invoiced';

        $invoice->milestone->update(['status' => $status]);
    }

    private function formatCurrency(float $value): float
    {
        return round($value, 2);
    }
}
