<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateClientProjectRequest;
use App\Http\Requests\UpdateProjectApprovalRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Services\ProjectService;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $service) {}

    private function mapProject(Project $project): array
    {
        return [
            'id'                   => $project->id,
            'name'                 => $project->name,
            'description'          => $project->description,
            'price'                => (float) $project->price,
            'default_tax_rate'     => (float) $project->default_tax_rate,
            'admin_counter_price'  => $project->admin_counter_price  ? (float) $project->admin_counter_price  : null,
            'client_counter_price' => $project->client_counter_price ? (float) $project->client_counter_price : null,
            'last_counter_by'      => $project->last_counter_by,
            'client'               => $project->client?->company_name,
            'approval_status'      => $project->approval_status,
            'milestones_count'     => $project->milestones_count,
            'is_archived'          => $project->isArchived(),
            'milestones'           => $project->milestones->map(fn (ProjectMilestone $milestone) => [
                'id'             => $milestone->id,
                'title'          => $milestone->title,
                'amount'         => (float) $milestone->amount,
                'status'         => $milestone->status,
                'invoice_id'     => $milestone->invoice?->id,
                'tax_rate'       => $milestone->invoice ? (float) $milestone->invoice->tax_rate : null,
                'tax_amount'     => $milestone->invoice ? (float) $milestone->invoice->tax_amount : null,
                'total_due'      => $milestone->invoice ? (float) $milestone->invoice->total_amount_due : null,
                'invoice_status' => $milestone->invoice?->status,
                'amount_paid'    => $milestone->invoice
                    ? (float) $milestone->invoice->payments->sum('amount_paid')
                    : 0.0,
            ]),
        ];
    }

    // 1. index — paginated, with optional ?show=archived toggle
    public function index()
    {
        if (request()->expectsJson()) {
            return response()->json(
                Project::with('client')->active()->latest()->paginate(10)// SALA  - NEED FURTHER OPTIMIZATION
            );
        }

        $showArchived = request()->boolean('show_archived');

        $query = Project::with('client')// SALA  - NEED FURTHER OPTIMIZATION
            ->with(['milestones' => fn ($q) => $q->with('invoice.payments')->orderBy('id')])
            ->withCount('milestones')
            ->latest();

        $query = $showArchived ? $query->archived() : $query->active();

        $paginator = $query->paginate(10)->withQueryString();

        return Inertia::render('Projects', [
            'projects'      => $paginator->getCollection()->map(fn (Project $p) => $this->mapProject($p)),
            'show_archived' => $showArchived,
            'pagination'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
        ]);
    }

    // 2. create
    public function create()
    {
        return response()->json(['clients' => $this->clientForCurrentUser()]);
    }

    public function createInertia()
    {
        return Inertia::render('CreateProject', [
            'clients' => $this->clientForCurrentUser(),
        ]);
    }

    // 3. store
    public function store(StoreProjectRequest $request)
    {
        $project = $this->service->createProject($request->validated());

        if ($request->expectsJson()) {
            return response()->json($project->load('client'), 201);
        }

        return redirect()->route('projects.mine')->with('success', 'Project created successfully.');
    }

    public function mine()
    {
        $user   = request()->user();
        $client = Client::where('email', $user->email)->first();

        $projects = $client
            ? $client->projects()
                ->with(['milestones' => fn ($q) => $q->with('invoice.payments')->orderBy('id')])// SALA  - NEED FURTHER OPTIMIZATION
                ->withCount('milestones')
                ->active()
                ->latest()
                ->get()
                ->map(fn (Project $project) => $this->mapProject($project))
            : collect();

        // Also pass pending/countered proposals so client can see/act on them
        $proposals = $client
            ? $client->projects()
                ->whereIn('approval_status', ['pending', 'countered', 'rejected'])
                ->whereNull('archived_at')
                ->latest()
                ->get()
                ->map(fn (Project $p) => [
                    'id'                   => $p->id,
                    'name'                 => $p->name,
                    'description'          => $p->description,
                    'price'                => (float) $p->price,
                    'default_tax_rate'     => (float) $p->default_tax_rate,
                    'admin_counter_price'  => $p->admin_counter_price  ? (float) $p->admin_counter_price  : null,
                    'client_counter_price' => $p->client_counter_price ? (float) $p->client_counter_price : null,
                    'last_counter_by'      => $p->last_counter_by,
                    'approval_status'      => $p->approval_status,
                ])
            : collect();

        return Inertia::render('Projects', [
            'projects'  => $projects,
            'proposals' => $proposals,
        ]);
    }

    private function clientForCurrentUser(): array
    {
        $user = request()->user();
        if (! $user?->isUser()) return [];
        $client = Client::where('email', $user->email)->first(['id', 'company_name']);
        return $client ? [$client] : [];
    }

    // 4. update
    public function update(UpdateProjectRequest $request, Project $project)
    {
        $updated = $this->service->updateProject($project, $request->validated());

        if ($request->expectsJson()) {
            return response()->json($updated->load('client'));
        }

        return redirect()->route('projects.index')->with('success', 'Project updated successfully.');
    }

    public function updateClient(UpdateClientProjectRequest $request, Project $project)
    {
        $this->service->updateProject($project, $request->validated());
        return redirect()->route('projects.mine')->with('success', 'Project updated successfully.');
    }

    public function updateApproval(UpdateProjectApprovalRequest $request, Project $project)
    {
        $project->update(['approval_status' => $request->validated('status')]);
        return redirect()->route('projects.index');
    }

    // Admin counters with a different price
    public function counter(Project $project)
    {
        // HLING FORM REQUEST
        $data = request()->validate([
            'admin_counter_price' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);

        // ADD DB TRANSACTION/ LOCK FOR UPDATE
        $project->update([
            'admin_counter_price'  => round((float) $data['admin_counter_price'], 2),
            'client_counter_price' => null,  // clear client's last counter — admin has the floor
            'last_counter_by'      => 'admin',
            'approval_status'      => 'countered',
        ]);

        return redirect()->route('invoices.index')->with('success', 'Counter offer sent to client.');
    }

    // Client counters back — allowed whenever status=countered and it was admin's last move
    // Also allowed when last_counter_by is null (legacy rows where admin countered before the column existed)
    public function clientCounter(Project $project)
    {
        $canCounter = $project->approval_status === 'countered'
            && in_array($project->last_counter_by, ['admin', null], true);

        if (! $canCounter) {
            abort(403, 'It is not your turn to counter.');
        }

        $data = request()->validate([
            'client_counter_price' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);

        $project->update([
            'client_counter_price' => round((float) $data['client_counter_price'], 2),
            'last_counter_by'      => 'client',
            'approval_status'      => 'countered',
        ]);

        return redirect()->route('invoices.index')->with('success', 'Counter offer sent to admin.');
    }

    // Client agrees to the admin's latest counter
    public function agreeCounter(Project $project)
    {
        if ($project->approval_status !== 'countered') {
            abort(403);
        }

        // Agree to whatever the admin last put on the table
        $agreedPrice = $project->last_counter_by === 'admin' || $project->last_counter_by === null
            ? ($project->admin_counter_price ?? $project->price)
            : $project->price;

        $project->update([
            'price'            => $agreedPrice,
            'approval_status'  => 'accepted',
            'client_agreed_at' => now(),
        ]);

        return redirect()->route('projects.mine')->with('success', 'You agreed to the counter offer. Your project is now active.');
    }

    // Admin agrees to client's counter — allowed whenever client has the last counter
    public function agreeClientCounter(Project $project)
    {
        $canAgree = $project->approval_status === 'countered'
            && $project->last_counter_by === 'client'
            && $project->client_counter_price !== null;

        if (! $canAgree) {
            abort(403, 'No client counter to accept.');
        }

        $project->update([
            'price'            => $project->client_counter_price,
            'approval_status'  => 'accepted',
            'client_agreed_at' => now(),
        ]);

        return redirect()->route('invoices.index')->with('success', "Accepted the client's counter. Project is now active.");
    }

    // Client rejects — close the negotiation
    public function rejectByClient(Project $project)
    {
        if (! in_array($project->approval_status, ['pending', 'countered'])) {
            abort(403);
        }

        $project->update(['approval_status' => 'rejected']);

        return redirect()->route('invoices.index')->with('success', 'Proposal rejected.');
    }
    public function archive(Project $project)
    {
        $project->update(['archived_at' => now()]);
        return redirect()->back()->with('success', "{$project->name} has been archived.");
    }

    // Restore an archived project
    public function unarchive(Project $project)
    {
        $project->update(['archived_at' => null]);
        return redirect()->back()->with('success', "{$project->name} has been restored.");
    }
}
