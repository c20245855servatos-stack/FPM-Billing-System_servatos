<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim(strip_tags((string) $this->name)),
            ]);
        }

        if ($this->has('client_id')) {
            $this->merge([
                'client_id' => (int) $this->client_id,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'client_id' => [
                'required',
                'integer',
                'exists:clients,id',
            ],
            'name' => [
                'required',
                'string',
                'min:2',
                'max:150',
                'regex:/\pL/u',
                Rule::unique('projects', 'name')
                    ->where(fn ($q) => $q->where('client_id', $this->input('client_id'))),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required' => 'A client is required.',
            'client_id.exists'   => 'The selected client does not exist.',
            'name.required'      => 'A project name is required.',
            'name.min'           => 'The project name must be at least 2 characters.',
            'name.regex'         => 'The project name must contain at least one letter.',
            'name.unique'        => 'This client already has a project with that name.',
        ];
    }
}
