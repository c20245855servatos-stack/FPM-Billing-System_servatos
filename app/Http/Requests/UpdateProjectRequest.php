<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $patch = [];

        if ($this->has('name')) {
            $patch['name'] = trim(strip_tags((string) $this->name));
        }

        if ($this->has('client_id')) {
            $patch['client_id'] = (int) $this->client_id;
        }

        if ($patch) {
            $this->merge($patch);
        }
    }

    public function rules(): array
    {
        // Fall back to the existing project's client_id when it isn't being changed
        $clientId = $this->input('client_id', $this->route('project')?->client_id);

        return [
            'client_id' => ['sometimes', 'integer', 'exists:clients,id'],
            'name' => [
                'sometimes',
                'string',
                'min:2',
                'max:150',
                'regex:/\pL/u',
                Rule::unique('projects', 'name')
                    ->where(fn ($q) => $q->where('client_id', $clientId))
                    ->ignore($this->route('project')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex'  => 'The project name must contain at least one letter.',
            'name.unique' => 'This client already has a project with that name.',
        ];
    }
}
