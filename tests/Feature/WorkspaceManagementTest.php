<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkspaceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_create_a_ready_to_use_workspace(): void
    {
        $admin = User::factory()->create(['is_superadmin' => true]);

        $response = $this->actingAs($admin)->postJson('/api/workspaces', [
            'name' => 'Mi hogar',
            'type' => 'household',
            'currency' => 'EUR',
        ])->assertCreated()->assertJsonPath('name', 'Mi hogar');

        $workspaceId = $response->json('id');
        $this->assertDatabaseHas('workspace_members', ['workspace_id' => $workspaceId, 'user_id' => $admin->id, 'role' => 'owner']);
        $this->assertDatabaseHas('accounts', ['workspace_id' => $workspaceId, 'name' => 'Cuenta principal']);
        $this->assertDatabaseCount('categories', 5);
    }

    public function test_regular_user_cannot_create_a_workspace(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/workspaces', [
            'name' => 'No permitido',
            'type' => 'household',
            'currency' => 'EUR',
        ])->assertForbidden();
    }
}
