<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoicePaymentRequest extends FormRequest
{
    /**
     * Valid payment method identifiers.
     * Keep this in sync with the InvoicePayment model and any frontend select lists.
     */
    public const METHODS = ['bank_transfer', 'gcash', 'cash', 'check', 'paypal', 'other'];

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $patch = [];

        if ($this->has('amount_paid')) {
            // Strip currency symbols and thousands separators before validation
            $raw = preg_replace('/[^\d.]/', '', (string) $this->amount_paid);
            $patch['amount_paid'] = $raw !== '' ? (float) $raw : $this->amount_paid;
        }

        if ($this->has('method')) {
            $patch['method'] = strtolower(trim((string) $this->method));
        }

        if ($this->has('paid_at')) {
            $patch['paid_at'] = trim((string) $this->paid_at);
        }

        if ($patch) {
            $this->merge($patch);
        }
    }

    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $balance = $invoice ? (float) $invoice->balanceDue() : 0;

        return [
            'amount_paid' => [
                'required',
                'numeric',
                // At most two decimal places
                'regex:/^\d+(\.\d{1,2})?$/',
                'gt:0',
                'lte:' . $balance,
            ],
            'paid_at' => [
                'sometimes',
                'nullable',
                'date',
                'date_format:Y-m-d',
                // Payments cannot be dated in the future
                'before_or_equal:today',
            ],
            'method' => [
                'sometimes',
                'nullable',
                'string',
                'in:' . implode(',', self::METHODS),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'amount_paid.required'     => 'A payment amount is required.',
            'amount_paid.gt'           => 'Payment amount must be greater than zero.',
            'amount_paid.lte'          => 'Payment amount cannot exceed the invoice balance.',
            'amount_paid.regex'        => 'The amount must have at most two decimal places.',
            'paid_at.date_format'      => 'The payment date must be in YYYY-MM-DD format.',
            'paid_at.before_or_equal'  => 'The payment date cannot be in the future.',
            'method.in'                => 'Payment method must be one of: ' . implode(', ', self::METHODS) . '.',
        ];
    }
}
