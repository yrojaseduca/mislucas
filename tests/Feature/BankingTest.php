<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankTransaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class BankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_select_a_spanish_bank_and_start_authorization(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $workspace->members()->create(['user_id' => $user->id, 'display_name' => 'Juan', 'role' => 'owner']);
        config()->set('services.gocardless', ['secret_id' => 'id', 'secret_key' => 'key', 'redirect_uri' => 'http://localhost/api/bank/callback', 'api_url' => 'https://bank.test/api/v2', 'country' => 'ES']);
        Cache::forget('gocardless.access_token');
        Http::fake([
            'https://bank.test/api/v2/token/new/' => Http::response(['access' => 'app-token', 'access_expires' => 86400]),
            'https://bank.test/api/v2/institutions/*' => Http::response([['id' => 'BBVA_ES', 'name' => 'BBVA']]),
            'https://bank.test/api/v2/requisitions/' => Http::response(['id' => 'req-1', 'link' => 'https://bank.test/authorize']),
        ]);

        $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/bank/connect", ['institution_id' => 'BBVA_ES'])
            ->assertOk()->assertJsonPath('authorization_url', 'https://bank.test/authorize');

        $this->assertDatabaseHas('bank_connections', ['workspace_id' => $workspace->id, 'provider' => 'gocardless', 'external_id' => 'req-1', 'provider_name' => 'BBVA']);
    }

    public function test_member_can_accept_a_banking_operation_as_a_movement(): void
    {
        [$user, $workspace, $member, $bankTransaction] = $this->bankingScenario();
        $account = Account::query()->create(['workspace_id' => $workspace->id, 'name' => 'Banco']);

        $response = $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/bank-transactions/{$bankTransaction->id}/accept", [
            'type' => 'expense', 'amount' => 2450, 'occurred_at' => '2026-08-24', 'description' => 'Supermercado',
            'account_id' => $account->id, 'category_id' => null, 'paid_by_member_id' => $member->id, 'notes' => null,
            'splits' => [['member_id' => $member->id, 'amount' => 2450, 'percentage' => 100]],
            'recurrence' => null, 'debt_payment' => null,
        ])->assertCreated()->assertJsonPath('description', 'Supermercado');

        $bankTransaction->refresh();
        $this->assertSame('accepted', $bankTransaction->status);
        $this->assertSame($response->json('id'), $bankTransaction->transaction_id);
    }

    public function test_member_can_dismiss_a_banking_operation(): void
    {
        [$user, $workspace, , $bankTransaction] = $this->bankingScenario();

        $this->actingAs($user)
            ->deleteJson("/api/workspaces/{$workspace->id}/bank-transactions/{$bankTransaction->id}")
            ->assertNoContent();

        $this->assertSame('dismissed', $bankTransaction->refresh()->status);
    }

    private function bankingScenario(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $member = $workspace->members()->create(['user_id' => $user->id, 'display_name' => 'Juan', 'role' => 'owner']);
        $connection = $workspace->bankConnections()->create(['user_id' => $user->id, 'access_token' => 'encrypted-at-rest']);
        $bankAccount = $connection->accounts()->create(['external_id' => 'account-1', 'kind' => 'account', 'display_name' => 'Cuenta bancaria', 'currency' => 'EUR']);
        $bankTransaction = BankTransaction::query()->create(['bank_account_id' => $bankAccount->id, 'external_id' => 'bank-movement-1', 'type' => 'expense', 'amount' => 2450, 'occurred_at' => '2026-08-24', 'description' => 'Supermercado']);

        return [$user, $workspace, $member, $bankTransaction];
    }
}
