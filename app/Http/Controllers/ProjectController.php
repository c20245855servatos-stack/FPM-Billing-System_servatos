<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Client;
use App\Models\Project;
use App\Services\DomainService;
use Inertia\Inertia;

class ProjectController extends Controller
{
    // Inject the Service into the controller
    public function __construct(private DomainService $service) {}

    // 1. Retrieve paginated records with eager-loaded relationships
    public function index()
    {
        $projects = Project::with('client')
            ->latest()
            ->paginate(15);

        return response()->json($projects);
    }

    // 2. Return creation interface or required dependency data
    public function create()
    {
        return response()->json([
            'clients' => Client::select('id', 'company_name', 'email')->get(),
        ]);
    }

    public function createInertia()
    {
        return Inertia::render('CreateProject', [
            'clients' => Client::select('id', 'company_name')->orderBy('company_name')->get(),
        ]);
    }

    // 3. Validate and delegate to Service
    public function store(StoreProjectRequest $request)
    {
        $project = $this->service->createProject($request->validated());

        if ($request->expectsJson()) {
            return response()->json($project->load('client'), 201);
        }

        return redirect()->route('projects.create')->with('success', 'Project created successfully.');
    }

    // 4. Validate and delegate update to Service
    public function update(UpdateProjectRequest $request, Project $project)
    {
        $updated = $this->service->updateProject($project, $request->validated());

        return response()->json($updated->load('client'));
    }
}
