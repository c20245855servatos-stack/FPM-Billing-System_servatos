<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'company_name' => 'Test Company',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('clients', [
            'company_name' => 'Test Company',
            'email' => 'test@example.com',
        ]);
    }

    public function test_registration_reuses_an_existing_client_with_the_same_email()
    {
        Client::create([
            'company_name' => 'Existing Company',
            'email' => 'test@example.com',
        ]);

        $this->post('/register', [
            'name' => 'Test User',
            'company_name' => 'New Company Name',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('clients', [
            'company_name' => 'Existing Company',
            'email' => 'test@example.com',
        ]);
    }
}
