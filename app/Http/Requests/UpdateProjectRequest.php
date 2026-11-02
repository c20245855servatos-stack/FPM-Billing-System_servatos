<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'sometimes' means the field is only validated if it is present in the request
            'client_id' => ['sometimes', 'integer', 'exists:clients,id'],
            'name' => [
                'sometimes',
                'string',
                'min:2',
                'max:150',
                // Ensure the name is unique, but ignore the current project's ID
                Rule::unique('projects', 'name')->ignore($this->route('project')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Another project already uses this name.',
        ];
    }
}
