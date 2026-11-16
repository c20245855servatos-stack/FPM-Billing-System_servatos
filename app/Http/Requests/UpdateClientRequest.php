<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $patch = [];

        if ($this->has('company_name')) {
            $patch['company_name'] = trim(strip_tags((string) $this->company_name));
        }

        if ($this->has('email')) {
            $patch['email'] = strtolower(trim(strip_tags((string) $this->email)));
        }

        if ($patch) {
            $this->merge($patch);
        }
    }

    public function rules(): array
    {
        return [
            'company_name' => [
                'sometimes',
                'string',
                'min:2',
                'max:150',
                'regex:/\pL/u',
            ],
            'email' => [
                'sometimes',
                'email:rfc,dns',
                'max:150',
                'lowercase',
                Rule::unique('clients', 'email')->ignore($this->route('client')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.regex' => 'The company name must contain at least one letter.',
            'email.email'        => 'Please enter a valid email address.',
            'email.unique'       => 'Another client already uses this email address.',
        ];
    }
}
