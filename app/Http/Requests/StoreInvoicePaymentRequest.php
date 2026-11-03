<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $balance = $invoice ? (float) $invoice->balanceDue() : 0;

        return [
            'amount_paid' => [
                'required',
                'numeric',
                'gt:0',
                'lte:'.$balance,
            ],
            'paid_at' => ['sometimes', 'nullable', 'date'],
            'method' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount_paid.gt' => 'Payment amount must be greater than zero.',
            'amount_paid.lte' => 'Payment amount cannot exceed the invoice balance.',
        ];
    }
}
