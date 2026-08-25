<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_superadmin_can_open_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/admin')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_superadmin' => true]))->getJson('/api/admin')->assertOk();
    }

    public function test_admin_can_archive_workspace_and_deactivate_another_user(): void
    {
        $admin = User::factory()->create(['is_superadmin' => true]);
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);

        $this->actingAs($admin)->putJson("/api/admin/workspaces/{$workspace->id}", [
            'name' => 'Casa familiar', 'type' => 'household', 'currency' => 'EUR', 'archived' => true,
        ])->assertOk();
        $this->assertNotNull($workspace->fresh()->archived_at);

        $this->actingAs($admin)->putJson("/api/admin/users/{$user->id}", [
            'name' => $user->name, 'is_active' => false, 'is_superadmin' => false,
        ])->assertOk();
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_admin_cannot_demote_self_or_delete_used_category(): void
    {
        $admin = User::factory()->create(['is_superadmin' => true]);
        $this->actingAs($admin)->putJson("/api/admin/users/{$admin->id}", [
            'name' => $admin->name, 'is_active' => true, 'is_superadmin' => false,
        ])->assertUnprocessable();

        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $member = $workspace->members()->create(['user_id' => $admin->id, 'display_name' => $admin->name, 'role' => 'owner']);
        $category = $workspace->categories()->create(['name' => 'Compra', 'kind' => 'expense']);
        Transaction::query()->create(['workspace_id' => $workspace->id, 'category_id' => $category->id, 'paid_by_member_id' => $member->id, 'created_by_user_id' => $admin->id, 'type' => 'expense', 'amount' => 100, 'occurred_at' => now(), 'description' => 'Prueba']);

        $this->actingAs($admin)->deleteJson("/api/admin/workspaces/{$workspace->id}/categories/{$category->id}")->assertUnprocessable();
        $this->assertTrue(Category::query()->whereKey($category->id)->exists());
    }
}
