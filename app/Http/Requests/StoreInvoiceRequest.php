<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $patch = [];

        if ($this->has('milestone_id')) {
            $patch['milestone_id'] = (int) $this->milestone_id;
        }

        if ($this->has('tax_rate')) {
            // Accept "7.5" or "0.075"; values > 1 are treated as a percentage and converted
            $rate = (float) $this->tax_rate;
            $patch['tax_rate'] = $rate > 1 ? round($rate / 100, 4) : $rate;
        }

        if ($patch) {
            $this->merge($patch);
        }
    }

    public function rules(): array
    {
        return [
            'milestone_id' => [
                'required',
                'integer',
                'exists:project_milestones,id',
            ],
            'tax_rate' => [
                'required',
                'numeric',
                'min:0',
                'max:1',
                // At most four decimal places (e.g. 0.0750)
                'regex:/^\d(\.\d{1,4})?$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'milestone_id.required' => 'An invoice must reference a milestone.',
            'milestone_id.exists'   => 'The referenced milestone does not exist.',
            'tax_rate.required'     => 'A tax rate is required.',
            'tax_rate.min'          => 'The tax rate cannot be negative.',
            'tax_rate.max'          => 'Tax rate must be a decimal between 0 and 1 (e.g. 0.075 for 7.5%).',
            'tax_rate.regex'        => 'The tax rate must have at most four decimal places.',
        ];
    }
}
