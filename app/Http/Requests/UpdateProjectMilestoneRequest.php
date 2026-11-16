<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectMilestoneRequest extends FormRequest
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
            'project_id' => ['sometimes', 'integer', 'exists:projects,id'],
            'title'      => [
                'sometimes',
                'string',
                'min:2',
                'max:150',
                'regex:/\pL/u',
            ],
            'amount'     => [
                'sometimes',
                'numeric',
                'min:0.01',
                'max:9999999999.99',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'due_date'   => [
                'sometimes',
                'date',
                'date_format:Y-m-d',
            ],
            'status'     => [
                'sometimes',
                'string',
                'in:pending,invoiced,paid',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.exists'    => 'The selected project does not exist.',
            'title.regex'          => 'The title must contain at least one letter.',
            'amount.min'           => 'The amount must be greater than zero.',
            'amount.regex'         => 'The amount must have at most two decimal places.',
            'due_date.date_format' => 'The due date must be in YYYY-MM-DD format.',
            'status.in'            => 'Status must be one of: pending, invoiced, paid.',
        ];
    }
}
