<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('amount_paid')) {
            $raw = preg_replace('/[^\d.]/', '', (string) $this->amount_paid);
            $this->merge(['amount_paid' => $raw !== '' ? (float) $raw : $this->amount_paid]);
        }

        if ($this->has('method')) {
            $this->merge(['method' => strtolower(trim((string) $this->method))]);
        }
    }

    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $payment = $this->route('payment');
        $adjustedBalance = $invoice
            ? (float) $invoice->balanceDue() + (float) ($payment?->amount_paid ?? 0)
            : 0;

        return [
            'amount_paid' => [
                'required',
                'numeric',
                'regex:/^\d+(\.\d{1,2})?$/',
                'gt:0',
                'lte:' . $adjustedBalance,
            ],
            'paid_at' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'before_or_equal:today'],
            'method' => [
                'sometimes',
                'nullable',
                'string',
                'in:' . implode(',', StoreInvoicePaymentRequest::METHODS),
            ],
        ];
    }
}
