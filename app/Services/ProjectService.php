<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            $project = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            $milestones = $project->milestones()
                ->with('invoice')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $milestoneCount = array_key_exists('milestones_count', $data)
                ? (int) $data['milestones_count']
                : $milestones->count();
            $totalCents = (int) round((float) ($data['price'] ?? $project->price) * 100);
            $planChanged = $milestoneCount !== $milestones->count()
                || $totalCents !== (int) round((float) $project->price * 100);
            $milestoneTitles = $data['milestone_titles'] ?? null;
            $titlesChanged = false;
            if (is_array($milestoneTitles)) {
                foreach ($milestones as $index => $milestone) {
                    if (($milestoneTitles[$index] ?? $milestone->title) !== $milestone->title) {
                        $titlesChanged = true;
                        break;
                    }
                }
            }
            $lockedMilestones = $milestones->filter(
                fn (ProjectMilestone $milestone) => $milestone->status !== 'pending' || $milestone->invoice !== null
            );

            if ($planChanged && $lockedMilestones->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'milestones_count' => 'The milestone count and project total cannot change after a milestone is invoiced or paid.',
                ]);
            }

            if ($titlesChanged && $lockedMilestones->isNotEmpty()) {
                foreach ($milestones as $index => $milestone) {
                    if (
                        ($milestone->status !== 'pending' || $milestone->invoice !== null)
                        && ($milestoneTitles[$index] ?? $milestone->title) !== $milestone->title
                    ) {
                        throw ValidationException::withMessages([
                            "milestone_titles.{$index}" => 'A milestone name cannot change after it is invoiced or paid.',
                        ]);
                    }
                }
            }

            if ($milestoneCount > 0 && $totalCents < $milestoneCount) {
                throw ValidationException::withMessages([
                    'milestones_count' => 'The project total must provide at least 0.01 for each milestone.',
                ]);
            }

            $project->update($this->sanitizeProject($data));

            if ($planChanged || $titlesChanged) {
                $this->syncMilestones($project, $milestones, $milestoneCount, $totalCents, $milestoneTitles, $planChanged);
            }

            return $project->fresh();
        });
    }

    private function syncMilestones(
        Project $project,
        EloquentCollection $milestones,
        int $count,
        int $totalCents,
        ?array $titles,
        bool $rebalanceAmounts,
    ): void
    {
        if ($milestones->count() > $count) {
            $milestones->slice($count)->each(fn (ProjectMilestone $milestone) => $milestone->delete());
            $milestones = $milestones->take($count)->values();
        }

        while ($milestones->count() < $count) {
            $index = $milestones->count();
            $milestones->push($project->milestones()->create([
                'title' => $titles[$index] ?? 'Milestone '.($index + 1),
                'amount' => '0.00',
                'due_date' => today()->addMonthsNoOverflow($index + 1)->toDateString(),
                'status' => 'pending',
            ]));
        }

        if ($count === 0) {
            return;
        }

        $baseAmount = $count > 0 ? intdiv($totalCents, $count) : 0;
        $remainder = $count > 0 ? $totalCents % $count : 0;

        foreach ($milestones->values() as $index => $milestone) {
            $updates = [];

            if (is_array($titles) && isset($titles[$index])) {
                $updates['title'] = trim(strip_tags((string) $titles[$index]));
            }

            if ($rebalanceAmounts) {
                $amountCents = $baseAmount + ($index < $remainder ? 1 : 0);
                $updates['amount'] = number_format($amountCents / 100, 2, '.', '');
            }

            if ($updates) {
                $milestone->update($updates);
            }
        }
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
        if (isset($data['price'])) {
            $clean['price'] = round((float) $data['price'], 2);
        }
        if (array_key_exists('default_tax_rate', $data)) {
            $rate = (float) $data['default_tax_rate'];
            $clean['default_tax_rate'] = $rate > 1 ? round($rate / 100, 4) : round($rate, 4);
        }
        if (array_key_exists('description', $data)) {
            $clean['description'] = $data['description'] !== null
                ? trim(strip_tags((string) $data['description']))
                : null;
        }

        return $clean;
    }
}
