<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class WorkspaceInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_and_new_user_can_accept_once(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $workspace->members()->create(['user_id' => $owner->id, 'display_name' => $owner->name, 'role' => 'owner']);

        $url = $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->id}/invitations", [
            'email' => 'nueva@example.com',
        ])->assertCreated()->json('url');
        $token = basename($url);
        Auth::logout();

        $this->postJson("/api/invitations/{$token}/accept", [
            'name' => 'Nueva',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertOk()->assertJsonPath('user.email', 'nueva@example.com');

        $user = User::query()->where('email', 'nueva@example.com')->firstOrFail();
        $this->assertTrue($workspace->members()->where('user_id', $user->id)->exists());
        $this->getJson("/api/invitations/{$token}")->assertNotFound();
    }

    public function test_regular_member_cannot_create_invitations(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $workspace->members()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => 'member']);

        $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/invitations", [
            'email' => 'otra@example.com',
        ])->assertForbidden();
    }

    public function test_existing_user_can_sign_in_and_accept_invitation(): void
    {
        $owner = User::factory()->create();
        $invited = User::factory()->create(['email' => 'existente@example.com', 'password' => 'secret123']);
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $workspace->members()->create(['user_id' => $owner->id, 'display_name' => $owner->name, 'role' => 'owner']);
        $url = $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->id}/invitations", ['email' => $invited->email])->json('url');
        Auth::logout();
        $token = basename($url);

        $this->getJson("/api/invitations/{$token}")->assertOk()->assertJsonPath('has_account', true);
        $this->postJson("/api/invitations/{$token}/accept", ['password' => 'secret123'])
            ->assertOk()->assertJsonPath('user.id', $invited->id);
        $this->assertTrue($workspace->members()->where('user_id', $invited->id)->exists());
    }

    public function test_superadmin_can_see_all_workspaces_and_invite(): void
    {
        $admin = User::factory()->create(['is_superadmin' => true]);
        $workspace = Workspace::query()->create(['name' => 'Negocio', 'type' => 'business', 'currency' => 'EUR']);

        $this->actingAs($admin)->getJson('/api/workspaces')->assertOk()->assertJsonPath('0.id', $workspace->id);
        $this->actingAs($admin)->postJson("/api/workspaces/{$workspace->id}/invitations", [
            'email' => 'persona@example.com',
        ])->assertCreated();
    }
}
