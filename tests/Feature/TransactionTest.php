<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Debt;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_create_an_equally_split_expense(): void
    {
        $user = User::factory()->create();
        $partner = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $payer = $workspace->members()->create(['user_id' => $user->id, 'display_name' => 'Juan', 'role' => 'owner']);
        $other = $workspace->members()->create(['user_id' => $partner->id, 'display_name' => 'María', 'role' => 'member']);
        $account = Account::query()->create(['workspace_id' => $workspace->id, 'name' => 'Cuenta conjunta']);
        $category = Category::query()->create(['workspace_id' => $workspace->id, 'name' => 'Compra']);

        $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/transactions", [
            'type' => 'expense',
            'amount' => 6840,
            'occurred_at' => '2026-08-21',
            'description' => 'Compra semanal',
            'account_id' => $account->id,
            'category_id' => $category->id,
            'paid_by_member_id' => $payer->id,
            'notes' => null,
            'splits' => [
                ['member_id' => $payer->id, 'amount' => 3420, 'percentage' => 50],
                ['member_id' => $other->id, 'amount' => 3420, 'percentage' => 50],
            ],
        ])->assertCreated()->assertJsonPath('amount', 6840);

        $this->assertDatabaseHas('transactions', ['description' => 'Compra semanal', 'amount' => 6840]);
        $this->assertDatabaseCount('transaction_splits', 2);

        $movement = Transaction::query()->firstOrFail();
        $this->actingAs($user)->putJson("/api/workspaces/{$workspace->id}/transactions/{$movement->id}", [
            'type' => 'expense', 'amount' => 7000, 'occurred_at' => '2026-08-22', 'description' => 'Compra corregida',
            'account_id' => $account->id, 'category_id' => $category->id, 'paid_by_member_id' => $payer->id,
            'notes' => null, 'splits' => [
                ['member_id' => $payer->id, 'amount' => 3500, 'percentage' => 50],
                ['member_id' => $other->id, 'amount' => 3500, 'percentage' => 50],
            ], 'recurrence' => null,
        ])->assertOk()->assertJsonPath('description', 'Compra corregida');
        $this->assertDatabaseHas('transactions', ['id' => $movement->id, 'amount' => 7000]);

        $this->actingAs($user)->deleteJson("/api/workspaces/{$workspace->id}/transactions/{$movement->id}")->assertNoContent();
        $this->assertDatabaseMissing('transactions', ['id' => $movement->id]);
    }

    public function test_non_member_cannot_create_a_movement(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Privado', 'type' => 'personal', 'currency' => 'EUR']);

        $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/transactions", [])->assertForbidden();
    }

    public function test_member_can_create_a_recurring_income_rule(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Negocio', 'type' => 'business', 'currency' => 'EUR']);
        $workspace->members()->create(['user_id' => $user->id, 'display_name' => 'Juan', 'role' => 'owner']);
        $account = Account::query()->create(['workspace_id' => $workspace->id, 'name' => 'Banco']);

        $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/transactions", [
            'type' => 'income', 'amount' => 120000, 'occurred_at' => '2026-08-21',
            'description' => 'Cuota mensual', 'account_id' => $account->id, 'category_id' => null,
            'paid_by_member_id' => null, 'notes' => null, 'splits' => [],
            'recurrence' => ['frequency' => 'monthly', 'ends_on' => '2027-08-21'],
        ])->assertCreated();

        $this->assertDatabaseHas('recurring_transactions', [
            'description' => 'Cuota mensual', 'frequency' => 'monthly',
        ]);
        $this->assertSame('2026-09-21', RecurringTransaction::query()->firstOrFail()->next_run_on->toDateString());
    }

    public function test_expense_can_reduce_a_debt_and_deletion_restores_its_principal(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $member = $workspace->members()->create(['user_id' => $user->id, 'display_name' => 'Juan', 'role' => 'owner']);
        $account = Account::query()->create(['workspace_id' => $workspace->id, 'name' => 'Banco']);
        $category = Category::query()->create(['workspace_id' => $workspace->id, 'name' => 'Hipoteca']);
        $debt = Debt::query()->create(['workspace_id' => $workspace->id, 'name' => 'Hipoteca', 'original_amount' => 5000000, 'outstanding_balance' => 5000000, 'annual_interest_rate' => 6]);

        $response = $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/transactions", [
            'type' => 'expense', 'amount' => 100000, 'occurred_at' => '2026-09-01', 'description' => 'Cuota hipoteca',
            'account_id' => $account->id, 'category_id' => $category->id, 'paid_by_member_id' => $member->id, 'notes' => null,
            'splits' => [['member_id' => $member->id, 'amount' => 100000, 'percentage' => 100]],
            'recurrence' => null, 'debt_payment' => ['debt_id' => $debt->id, 'interest_amount' => 25000],
        ])->assertCreated()->assertJsonPath('debt_payment.principal_amount', 75000);

        $debt->refresh();
        $this->assertSame(4925000, $debt->outstanding_balance);
        $this->deleteJson("/api/workspaces/{$workspace->id}/transactions/{$response->json('id')}")->assertNoContent();
        $this->assertSame(5000000, $debt->refresh()->outstanding_balance);
    }

    public function test_member_can_increase_and_remove_a_debt_increase(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $workspace->members()->create(['user_id' => $user->id, 'display_name' => 'Juan', 'role' => 'owner']);
        $debt = Debt::query()->create(['workspace_id' => $workspace->id, 'name' => 'Préstamo', 'original_amount' => 5000000, 'outstanding_balance' => 5000000, 'annual_interest_rate' => 5]);

        $response = $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/debts/{$debt->id}/increases", [
            'amount' => 600000,
            'occurred_at' => '2026-08-24',
            'description' => 'Capital adicional',
        ])->assertCreated()->assertJsonPath('amount', 600000);

        $this->assertSame(5600000, $debt->refresh()->original_amount);
        $this->assertSame(5600000, $debt->outstanding_balance);

        $this->deleteJson("/api/workspaces/{$workspace->id}/debts/{$debt->id}/increases/{$response->json('id')}")->assertNoContent();
        $this->assertSame(5000000, $debt->refresh()->original_amount);
        $this->assertSame(5000000, $debt->outstanding_balance);
    }

    public function test_debt_increase_cannot_be_removed_after_its_capital_was_amortized(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $workspace->members()->create(['user_id' => $user->id, 'display_name' => 'Juan', 'role' => 'owner']);
        $debt = Debt::query()->create(['workspace_id' => $workspace->id, 'name' => 'Préstamo', 'original_amount' => 5000000, 'outstanding_balance' => 100000, 'annual_interest_rate' => 5]);
        $increase = $debt->increases()->create(['amount' => 600000, 'occurred_at' => '2026-08-24']);

        $this->actingAs($user)
            ->deleteJson("/api/workspaces/{$workspace->id}/debts/{$debt->id}/increases/{$increase->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No se puede eliminar la ampliación porque parte de ese capital ya ha sido amortizado.');
    }
}
