<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $this->user()?->isAdmin()
            && $project instanceof Project
            && $project->approval_status === 'accepted';
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

        if ($this->has('price')) {
            $raw = preg_replace('/[^\d.]/', '', (string) $this->price);
            $patch['price'] = $raw !== '' ? (float) $raw : null;
        }

        if ($this->has('milestone_titles') && is_array($this->input('milestone_titles'))) {
            $patch['milestone_titles'] = array_values(array_map(
                fn ($title) => is_string($title) ? trim(strip_tags($title)) : $title,
                $this->input('milestone_titles'),
            ));
        }

        if ($this->has('default_tax_rate')) {
            $rate = (float) $this->default_tax_rate;
            $patch['default_tax_rate'] = $rate > 1 ? round($rate / 100, 4) : $rate;
        }

        if ($patch) {
            $this->merge($patch);
        }
    }

    public function rules(): array
    {
        // Fall back to the existing project's client_id when it isn't being changed
        $project = $this->route('project');
        $clientId = $this->input('client_id', $project?->client_id);
        $milestoneCount = (int) $this->input('milestones_count', $project?->milestones()->count() ?? 0);

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
            'price'            => ['sometimes', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'default_tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'milestones_count' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'milestone_titles' => [
                $this->has('milestones_count') ? 'present' : 'sometimes',
                'array',
                'size:'.$milestoneCount,
            ],
            'milestone_titles.*' => ['required', 'string', 'min:2', 'max:150', 'regex:/\pL/u'],
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
