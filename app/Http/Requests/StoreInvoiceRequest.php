<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'milestone_id' => ['required', 'integer', 'exists:project_milestones,id'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'milestone_id.required' => 'An invoice must reference a milestone.',
            'milestone_id.exists' => 'The referenced milestone does not exist.',
            'tax_rate.max' => 'Tax rate must be expressed as a decimal between 0 and 1 (e.g. 0.075).',
        ];
    }
}
