<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_suite_uses_an_isolated_in_memory_database(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_guest_cannot_access_workspaces(): void
    {
        $this->getJson('/api/workspaces')->assertUnauthorized();
    }

    public function test_user_can_login_access_workspaces_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonStructure(['user', 'csrf_token']);

        $this->getJson('/api/workspaces')->assertOk();
        $this->postJson('/api/logout')
            ->assertOk()
            ->assertJsonStructure(['csrf_token']);
        $this->getJson('/api/workspaces')->assertUnauthorized();
    }
}
