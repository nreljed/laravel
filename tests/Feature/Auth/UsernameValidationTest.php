<?php

namespace Tests\Feature\Auth;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\DashboardCore\Models\User;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class UsernameValidationTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_registration_rejects_username_with_spaces(): void
    {
        $response = $this->post('/register', [
            'username' => 'my user',
            'email' => 'myuser@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_registration_rejects_username_with_special_characters(): void
    {
        $response = $this->post('/register', [
            'username' => 'user!@#',
            'email' => 'user@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_registration_accepts_username_with_periods(): void
    {
        $response = $this->post('/register', [
            'username' => 'nstudio.webdesign',
            'email' => 'studio@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', ['username' => 'nstudio.webdesign']);
    }

    public function test_registration_rejects_username_with_hyphen_and_underscore(): void
    {
        $response = $this->post('/register', [
            'username' => 'my_user-name',
            'email' => 'underscore@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_registration_rejects_username_with_leading_trailing_or_consecutive_periods(): void
    {
        foreach (['.leading', 'trailing.', 'double..dot'] as $username) {
            $response = $this->post('/register', [
                'username' => $username,
                'email' => fake()->unique()->safeEmail(),
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

            $response->assertSessionHasErrors('username');
        }

        $this->assertGuest();
    }

    public function test_registration_normalizes_username_to_lowercase(): void
    {
        $response = $this->post('/register', [
            'username' => '  MyUser  ',
            'email' => 'myuser@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', ['username' => 'myuser']);
        $this->assertDatabaseMissing('users', ['username' => 'MyUser']);
    }

    public function test_registration_username_unique_is_case_insensitive(): void
    {
        User::factory()->create(['username' => 'nstudio']);

        $response = $this->post('/register', [
            'username' => 'NSTUDIO',
            'email' => 'nstudio2@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_admin_create_user_rejects_invalid_username(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.users.store'), [
                'username' => 'bad name!',
                'name' => 'Bad Name',
                'email' => 'bad@example.com',
                'password' => 'SecurePass123!',
            ]);

        $response->assertSessionHasErrors('username');
        $this->assertDatabaseMissing('users', ['email' => 'bad@example.com']);
    }

    public function test_admin_update_user_ignores_own_username_on_unique_check(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.admin.users.update', ['user' => $user->id]), [
                'username' => 'gooduser',
                'name' => 'Good User',
                'email' => $user->email,
            ]);

        $response->assertSessionHasNoErrors('username');
        $this->assertDatabaseHas('users', ['username' => 'gooduser']);
    }

    public function test_availability_check_is_case_insensitive(): void
    {
        User::factory()->create(['username' => 'nstudio']);

        $this->get(route('register.availability', ['field' => 'username', 'value' => 'NSTUDIO']))
            ->assertJson(['available' => false]);

        $this->get(route('register.availability', ['field' => 'username', 'value' => 'studioguy']))
            ->assertJson(['available' => true]);
    }

    public function test_login_by_username_is_case_insensitive(): void
    {
        User::factory()->create([
            'username' => 'nstudio',
            'email' => 'nstudio@example.com',
            'password' => 'Password123!',
            'email_verified_at' => now(),
        ]);

        $this->post('/login', [
            'username' => 'NSTUDIO',
            'password' => 'Password123!',
        ]);

        $this->assertAuthenticated();
    }
}
