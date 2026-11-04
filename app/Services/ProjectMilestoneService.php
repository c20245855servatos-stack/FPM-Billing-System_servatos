<?php

namespace App\Services;

use App\Models\ProjectMilestone;
use Illuminate\Support\Facades\DB;

class ProjectMilestoneService
{
    public function createMilestone(array $data): ProjectMilestone
    {
        return DB::transaction(function () use ($data) {
            return ProjectMilestone::create([
                'project_id' => (int) $data['project_id'],
                'title' => trim(strip_tags($data['title'])),
                'amount' => $this->formatCurrency((float) $data['amount']),
                'due_date' => $data['due_date'],
                'status' => 'pending',
            ]);
        });
    }

    public function updateMilestone(ProjectMilestone $milestone, array $data): ProjectMilestone
    {
        return DB::transaction(function () use ($milestone, $data) {
            if (isset($data['project_id'])) {
                $milestone->project_id = (int) $data['project_id'];
            }
            if (isset($data['title'])) {
                $milestone->title = trim(strip_tags($data['title']));
            }
            if (isset($data['amount'])) {
                $milestone->amount = $this->formatCurrency((float) $data['amount']);
            }
            if (isset($data['due_date'])) {
                $milestone->due_date = $data['due_date'];
            }
            if (isset($data['status'])) {
                $milestone->status = $data['status'];
            }

            $milestone->save();

            return $milestone->fresh(['project', 'invoice']);
        });
    }

    private function formatCurrency(float $value): float
    {
        return round($value, 2);
    }
}
