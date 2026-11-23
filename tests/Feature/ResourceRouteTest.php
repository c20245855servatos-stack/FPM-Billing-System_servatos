<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_access_project_index_but_not_project_creation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create([
            'company_name' => 'Admin Client',
            'email' => 'admin-client@example.com',
        ]);
        Project::create([
            'client_id' => $client->id,
            'name' => 'Admin project',
            'price' => 100,
        ]);

        $this->actingAs($admin)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Admin project');

        $this->actingAs($admin)
            ->getJson(route('projects.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('projects.create'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('projects.store'), [
                'name' => 'Admin project',
                'client_id' => 1,
                'price' => '100.00',
            ])
            ->assertForbidden();
    }

    public function test_client_accounts_can_create_projects_for_their_own_client_record(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'email' => 'client@example.com',
        ]);
        $client = Client::create([
            'company_name' => 'Client Company',
            'email' => $user->email,
        ]);
        $otherClient = Client::create([
            'company_name' => 'Other Company',
            'email' => 'other@example.com',
        ]);
        Project::create([
            'client_id' => $otherClient->id,
            'name' => 'Other client project',
            'price' => 80,
        ]);

        $this->actingAs($user)
            ->get(route('projects.create'))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'name' => 'New client project',
                'client_id' => $otherClient->id,
                'price' => '125.00',
            ])
            ->assertRedirect(route('projects.mine'));

        $this->assertDatabaseHas('projects', [
            'name' => 'New client project',
            'client_id' => $client->id,
        ]);

        $this->actingAs($user)
            ->get(route('projects.mine'))
            ->assertOk()
            ->assertSee('New client project')
            ->assertDontSee('Other client project');
    }

    public function test_only_accepted_projects_can_be_edited_by_admin_or_owner(): void
    {
        $client = Client::create([
            'company_name' => 'Approval Client',
            'email' => 'approval-client@example.com',
        ]);
        $user = User::factory()->create([
            'role' => 'user',
            'email' => $client->email,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $acceptedProject = Project::create([
            'client_id' => $client->id,
            'name' => 'Accepted project',
            'price' => 100,
        ]);
        $rejectedProject = Project::create([
            'client_id' => $client->id,
            'name' => 'Rejected project',
            'price' => 200,
        ]);

        $this->actingAs($user)
            ->patch(route('projects.mine.update', $acceptedProject), [
                'name' => 'Changed before approval',
                'price' => '110.00',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('projects.update', $acceptedProject), [
                'name' => 'Changed before approval',
                'price' => '110.00',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('projects.approval.update', $acceptedProject), ['status' => 'accepted'])
            ->assertRedirect(route('projects.index'));

        $this->actingAs($admin)
            ->patch(route('projects.update', $acceptedProject), [
                'name' => 'Accepted project edited by admin',
                'price' => '115.00',
            ])
            ->assertRedirect(route('projects.index'));

        $this->actingAs($admin)
            ->patch(route('projects.approval.update', $rejectedProject), ['status' => 'rejected'])
            ->assertRedirect(route('projects.index'));

        $this->actingAs($user)
            ->patch(route('projects.mine.update', $acceptedProject), [
                'name' => 'Accepted project edited',
                'price' => '125.00',
                'milestones_count' => '3',
                'milestone_titles' => ['Design', 'Build', 'Launch'],
            ])
            ->assertRedirect(route('projects.mine'));

        $this->actingAs($user)
            ->get(route('projects.mine'))
            ->assertOk()
            ->assertSee('Design');

        $milestones = ProjectMilestone::where('project_id', $acceptedProject->id)->orderBy('id')->get();
        $this->assertSame(
            ['Design', 'Build', 'Launch'],
            $milestones->pluck('title')->all(),
        );
        $this->assertSame(
            ['41.67', '41.67', '41.66'],
            $milestones->map(fn (ProjectMilestone $milestone) => $milestone->amount)->all(),
        );

        $this->actingAs($user)
            ->patch(route('projects.mine.update', $acceptedProject), [
                'name' => 'Accepted project edited',
                'price' => '125.00',
                'milestones_count' => '2',
                'milestone_titles' => ['Design', 'Build'],
            ])
            ->assertRedirect(route('projects.mine'));

        $this->assertSame(
            ['Design', 'Build'],
            ProjectMilestone::where('project_id', $acceptedProject->id)
                ->orderBy('id')
                ->get()
                ->pluck('title')
                ->all(),
        );
        $this->assertSame(
            ['62.50', '62.50'],
            ProjectMilestone::where('project_id', $acceptedProject->id)
                ->orderBy('id')
                ->get()
                ->map(fn (ProjectMilestone $milestone) => $milestone->amount)
                ->all(),
        );

        $this->actingAs($user)
            ->patch(route('projects.mine.update', $rejectedProject), [
                'name' => 'Rejected project edited',
                'price' => '225.00',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('projects.update', $rejectedProject), [
                'name' => 'Rejected project edited by admin',
                'price' => '225.00',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('projects', [
            'id' => $acceptedProject->id,
            'name' => 'Accepted project edited',
            'price' => '125.00',
            'approval_status' => 'accepted',
        ]);
        $this->assertDatabaseHas('projects', [
            'id' => $rejectedProject->id,
            'name' => 'Rejected project',
            'approval_status' => 'rejected',
        ]);
    }

    public function test_invoice_routes_are_available_to_admins(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->getJson(route('invoices.index'))
            ->assertOk();

        $this->actingAs($user)
            ->getJson(route('invoices.create'))
            ->assertOk();
    }

    public function test_client_and_milestone_routes_are_available_to_admins(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->getJson(route('clients.index'))
            ->assertOk();

        $this->actingAs($user)
            ->getJson(route('milestones.index'))
            ->assertOk();

        $this->actingAs($user)
            ->getJson(route('milestones.create'))
            ->assertOk();
    }

    public function test_admins_can_view_all_clients(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (range(1, 16) as $number) {
            Client::create([
                'company_name' => "Client {$number}",
                'email' => "client{$number}@example.com",
            ]);
        }

        $this->actingAs($admin)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('Client 16');
    }
}
