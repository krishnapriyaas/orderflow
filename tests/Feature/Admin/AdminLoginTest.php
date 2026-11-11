<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_form_is_shown(): void
    {
        $this->get('/login')->assertOk()->assertSee('Log in');
    }

    public function test_an_admin_can_log_in_and_is_sent_to_the_review_page(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->post('/login', ['email' => 'Admin@Example.com', 'password' => 'password'])
            ->assertRedirect('/admin/reviews');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->from('/login')
            ->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

        $this->assertGuest();
    }

    public function test_missing_fields_are_rejected(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertStatus(429);

        $this->assertGuest();
    }

    public function test_a_user_can_log_out(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_is_admin_cannot_be_mass_assigned(): void
    {
        $user = User::query()->create([
            'name' => 'Someone',
            'email' => 'someone@example.com',
            'password' => 'secret-password',
            'is_admin' => true,
        ]);

        $this->assertFalse($user->fresh()?->is_admin);
    }
}
