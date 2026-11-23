<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectMilestoneRequest;
use App\Http\Requests\UpdateProjectMilestoneRequest;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Services\ProjectMilestoneService;
use Illuminate\Http\RedirectResponse;

class ProjectMilestoneController extends Controller
{
    public function __construct(private ProjectMilestoneService $service) {}

    public function index()
    {
        return response()->json(
            ProjectMilestone::with(['project.client', 'invoice'])->latest()->paginate(15)
        );
    }

    public function create()
    {
        return response()->json([
            'projects' => Project::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreProjectMilestoneRequest $request)
    {
        $milestone = $this->service->createMilestone($request->validated());

        return response()->json($milestone->load(['project.client', 'invoice']), 201);
    }

    public function update(UpdateProjectMilestoneRequest $request, ProjectMilestone $milestone)
    {
        $updated = $this->service->updateMilestone($milestone, $request->validated());

        return response()->json($updated->load(['project.client', 'invoice']));
    }

    /**
     * Admin-only: update just the status of a milestone.
     * Used by the inline status dropdown on the Projects page.
     */
    public function updateStatus(ProjectMilestone $milestone): RedirectResponse
    {
        request()->validate([
            'status' => ['required', 'string', 'in:pending,invoiced,paid'],
        ]);

        $this->service->updateMilestone($milestone, [
            'status' => request()->input('status'),
        ]);

        return redirect()->back()->with('success', 'Milestone status updated.');
    }
}
