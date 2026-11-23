<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->isUser()
            && Client::where('email', $user->email)->exists();
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if ($user?->isUser()) {
            $this->merge([
                'client_id' => Client::where('email', $user->email)->value('id'),
            ]);
        }

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

        if ($this->has('price')) {
            $raw = preg_replace('/[^\d.]/', '', (string) $this->price);
            $this->merge(['price' => $raw !== '' ? (float) $raw : null]);
        }

        if ($this->has('description')) {
            $this->merge([
                'description' => trim(strip_tags((string) $this->description)),
            ]);
        }

        if ($this->has('default_tax_rate')) {
            $rate = (float) $this->default_tax_rate;
            // Accept percentage input like 12 and convert to decimal 0.12
            $this->merge(['default_tax_rate' => $rate > 1 ? round($rate / 100, 4) : $rate]);
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
            'price'            => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
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
