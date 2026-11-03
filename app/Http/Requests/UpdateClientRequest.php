<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'string', 'min:2', 'max:150'],
            'email' => [
                'sometimes',
                'email',
                'max:150',
                Rule::unique('clients', 'email')->ignore($this->route('client')),
            ],
        ];
    }

    public function messages(): array
    {
        return ['email.unique' => 'Another client already uses this email address.'];
    }
}
