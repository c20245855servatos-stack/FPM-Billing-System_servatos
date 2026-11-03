<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectMilestoneRequest;
use App\Http\Requests\UpdateProjectMilestoneRequest;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Services\DomainService;

class ProjectMilestoneController extends Controller
{
    public function __construct(private DomainService $service) {}

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
}
