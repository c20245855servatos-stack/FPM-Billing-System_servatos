<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function createProject(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            return Project::create($this->sanitizeProject($data));
        });
    }

    public function updateProject(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data) {
            $project->update($this->sanitizeProject($data));

            return $project->fresh();
        });
    }

    private function sanitizeProject(array $data): array
    {
        $clean = [];

        if (isset($data['client_id'])) {
            $clean['client_id'] = (int) $data['client_id'];
        }
        if (isset($data['name'])) {
            $clean['name'] = trim(strip_tags($data['name']));
        }

        return $clean;
    }
}
