<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('clients', 'email')],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'The client company name is required.',
            'email.required' => 'The client email address is required.',
            'email.unique' => 'A client already uses this email address.',
        ];
    }
}
