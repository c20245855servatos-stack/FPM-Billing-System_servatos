<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // <-- Import Rule

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool 
    { 
        return true; 
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'name'      => [
                'required', 
                'string', 
                'min:2', 
                'max:150',
                Rule::unique('projects', 'name') // <-- This checks the database!
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.exists' => 'The selected client does not exist.',
            'name.required'    => 'A project name is required.',
            'name.min'         => 'Project name must be at least 2 characters.',
            'name.unique'      => 'A project with this name already exists.', // <-- Custom Error Message
        ];
    }
}