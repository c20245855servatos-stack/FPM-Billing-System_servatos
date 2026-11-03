<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'status' => ['sometimes', 'in:unpaid,partial,paid'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status must be one of: unpaid, partial, paid.',
        ];
    }
}
