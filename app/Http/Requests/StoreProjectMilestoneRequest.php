<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $patch = [];

        if ($this->has('title')) {
            $patch['title'] = trim(strip_tags((string) $this->title));
        }

        if ($this->has('project_id')) {
            $patch['project_id'] = (int) $this->project_id;
        }

        if ($this->has('amount')) {
            // Normalize decimal separator and remove currency formatting
            $raw = preg_replace('/[^\d.]/', '', (string) $this->amount);
            $patch['amount'] = $raw !== '' ? (float) $raw : $this->amount;
        }

        if ($patch) {
            $this->merge($patch);
        }
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'title'      => [
                'required',
                'string',
                'min:2',
                'max:150',
                'regex:/\pL/u',
            ],
            'amount'     => [
                'required',
                'numeric',
                'min:0.01',
                'max:9999999999.99',
                // No more than two decimal places
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'due_date'   => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.required'  => 'A project is required.',
            'project_id.exists'    => 'The selected project does not exist.',
            'title.required'       => 'A milestone title is required.',
            'title.regex'          => 'The title must contain at least one letter.',
            'amount.required'      => 'An amount is required.',
            'amount.min'           => 'The amount must be greater than zero.',
            'amount.regex'         => 'The amount must have at most two decimal places.',
            'due_date.required'    => 'A due date is required.',
            'due_date.date_format' => 'The due date must be in YYYY-MM-DD format.',
            'due_date.after_or_equal' => 'The due date cannot be in the past.',
        ];
    }
}
