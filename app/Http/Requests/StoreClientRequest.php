<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only authenticated admin (Freelancer) accounts may create clients
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Sanitize input before validation runs.
     * Strip HTML tags and normalize whitespace so the validated data is
     * already clean when it reaches the service layer.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_name' => trim(strip_tags((string) $this->company_name)),
            'email'        => strtolower(trim(strip_tags((string) $this->email))),
        ]);
    }

    public function rules(): array
    {
        return [
            'company_name' => [
                'required',
                'string',
                'min:2',
                'max:150',
                // Reject strings that are only whitespace or punctuation
                'regex:/\pL/u',
            ],
            'email' => [
                'required',
                'email:rfc,dns',
                'max:150',
                'lowercase',
                Rule::unique('clients', 'email'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'The client company name is required.',
            'company_name.regex'    => 'The company name must contain at least one letter.',
            'email.required'        => 'The client email address is required.',
            'email.email'           => 'Please enter a valid email address.',
            'email.unique'          => 'A client already uses this email address.',
        ];
    }
}
