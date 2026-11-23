<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $project = $this->route('project');

        return $user?->isUser()
            && $project instanceof Project
            && $project->approval_status === 'accepted'
            && Client::where('email', $user->email)
                ->whereKey($project->client_id)
                ->exists();
    }

    protected function prepareForValidation(): void
    {
        $patch = [];

        if ($this->has('name')) {
            $patch['name'] = trim(strip_tags((string) $this->name));
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

        if ($patch) {
            $this->merge($patch);
        }
    }

    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:150',
                'regex:/\pL/u',
                Rule::unique('projects', 'name')
                    ->where(fn ($query) => $query->where('client_id', $project->client_id))
                    ->ignore($project),
            ],
            'price' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'milestones_count' => ['required', 'integer', 'min:0', 'max:100'],
            'milestone_titles' => ['present', 'array', 'size:'.$this->input('milestones_count')],
            'milestone_titles.*' => ['required', 'string', 'min:2', 'max:150', 'regex:/\pL/u'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'The project name must contain at least one letter.',
            'name.unique' => 'This client already has a project with that name.',
            'milestones_count.max' => 'A project cannot have more than 100 milestones.',
            'milestone_titles.*.required' => 'Every milestone must have a name.',
            'milestone_titles.*.regex' => 'Every milestone name must contain at least one letter.',
        ];
    }
}
