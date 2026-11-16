<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tax_rate')) {
            $rate = (float) $this->tax_rate;
            $this->merge([
                'tax_rate' => $rate > 1 ? round($rate / 100, 4) : $rate,
            ]);
        }

        if ($this->has('status')) {
            $this->merge([
                'status' => strtolower(trim((string) $this->status)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'tax_rate' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:1',
                'regex:/^\d(\.\d{1,4})?$/',
            ],
            'status' => [
                'sometimes',
                'string',
                'in:unpaid,partial,paid',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'tax_rate.max'   => 'Tax rate must be a decimal between 0 and 1 (e.g. 0.075 for 7.5%).',
            'tax_rate.regex' => 'The tax rate must have at most four decimal places.',
            'status.in'      => 'Status must be one of: unpaid, partial, paid.',
        ];
    }
}
