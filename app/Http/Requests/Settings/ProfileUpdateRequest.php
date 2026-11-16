<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $patch = [];

        if ($this->has('name')) {
            $patch['name'] = trim(strip_tags((string) $this->name));
        }

        if ($this->has('email')) {
            $patch['email'] = strtolower(trim(strip_tags((string) $this->email)));
        }

        if ($patch) {
            $this->merge($patch);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/\pL/u',
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email:rfc,dns',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex'  => 'The name must contain at least one letter.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }
}
