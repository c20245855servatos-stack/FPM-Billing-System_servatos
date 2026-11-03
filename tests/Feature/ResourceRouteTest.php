<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_routes_are_available_to_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('projects.create'))
            ->assertOk();
    }

    public function test_invoice_routes_are_available_to_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('invoices.index'))
            ->assertOk();

        $this->actingAs($user)
            ->getJson(route('invoices.create'))
            ->assertOk();
    }
}
