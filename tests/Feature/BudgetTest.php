<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_does_not_double_count_real_and_committed_expenses(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $member = $workspace->members()->create(['user_id' => $user->id, 'display_name' => 'Juan', 'role' => 'owner']);
        $account = Account::query()->create(['workspace_id' => $workspace->id, 'name' => 'Banco']);
        $category = Category::query()->create(['workspace_id' => $workspace->id, 'name' => 'Compra']);
        Budget::query()->create(['workspace_id' => $workspace->id, 'category_id' => $category->id, 'type' => 'expense', 'name' => 'Compra', 'month' => '2026-08-01', 'amount' => 50000]);
        Transaction::query()->create(['workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'paid_by_member_id' => $member->id, 'created_by_user_id' => $user->id, 'type' => 'expense', 'amount' => 10000, 'occurred_at' => '2026-08-05', 'description' => 'Supermercado']);
        Transaction::query()->create(['workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'paid_by_member_id' => $member->id, 'created_by_user_id' => $user->id, 'type' => 'expense', 'amount' => 90000, 'occurred_at' => '2026-07-05', 'description' => 'Gasto del mes anterior']);
        RecurringTransaction::query()->create(['workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'paid_by_member_id' => $member->id, 'created_by_user_id' => $user->id, 'type' => 'expense', 'amount' => 20000, 'description' => 'Compra recurrente', 'splits' => [], 'frequency' => 'monthly', 'next_run_on' => '2026-08-20']);

        $this->actingAs($user)->getJson("/api/workspaces/{$workspace->id}?month=2026-08")
            ->assertOk()
            ->assertJsonPath('plan.rows.0.budget', 50000)
            ->assertJsonPath('plan.rows.0.actual', 10000)
            ->assertJsonPath('plan.rows.0.committed', 20000)
            ->assertJsonPath('plan.rows.0.forecast', 50000)
            ->assertJsonPath('plan.rows.0.remaining', 20000)
            ->assertJsonPath('plan.summary.expected_expenses', 50000)
            ->assertJsonPath('summary.expenses', 10000)
            ->assertJsonCount(1, 'transactions')
            ->assertJsonPath('transactions.0.description', 'Supermercado')
            ->assertJsonPath('balances.0.paid', 10000);
    }

    public function test_member_can_create_and_update_a_monthly_budget(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $workspace->members()->create(['user_id' => $user->id, 'display_name' => 'Juan', 'role' => 'owner']);
        $category = Category::query()->create(['workspace_id' => $workspace->id, 'name' => 'Nómina', 'kind' => 'income']);

        $payload = ['category_id' => $category->id, 'type' => 'income', 'name' => 'Nómina', 'month' => '2026-08-01', 'amount' => 300000, 'rollover_policy' => 'expires', 'notes' => null];
        $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/budgets", $payload)->assertCreated();
        $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/budgets", [...$payload, 'amount' => 320000])->assertOk();

        $this->assertDatabaseCount('budgets', 1);
        $this->assertDatabaseHas('budgets', ['amount' => 320000]);
    }

    public function test_member_can_create_a_budget_category(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $workspace->members()->create(['user_id' => $user->id, 'display_name' => 'Juan', 'role' => 'owner']);

        $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/categories", [
            'name' => 'Ropa', 'kind' => 'expense', 'color' => '#84a940', 'icon' => null,
        ])->assertCreated()->assertJsonPath('name', 'Ropa');

        $this->assertDatabaseHas('categories', ['workspace_id' => $workspace->id, 'name' => 'Ropa', 'kind' => 'expense']);
    }

    public function test_month_override_does_not_change_the_base_plan_for_future_months(): void
    {
        Carbon::setTestNow('2026-08-21 10:00:00');
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $workspace->members()->create(['user_id' => $user->id, 'display_name' => 'Juan', 'role' => 'owner']);
        $category = Category::query()->create(['workspace_id' => $workspace->id, 'name' => 'Compra', 'kind' => 'expense']);
        $url = "/api/workspaces/{$workspace->id}";

        $this->actingAs($user)->putJson($url.'/monthly-budget-rules', ['rules' => [
            ['category_id' => $category->id, 'default_amount' => 50000, 'rollover_policy' => 'expires'],
        ]])->assertOk();
        $this->getJson($url.'?month=2026-08')->assertJsonPath('plan.rows.0.base_budget', 50000)->assertJsonPath('plan.rows.0.is_override', false);

        $this->postJson($url.'/budgets', ['category_id' => $category->id, 'type' => 'expense', 'name' => 'Compra', 'month' => '2026-08-01', 'amount' => 60000, 'rollover_policy' => 'expires', 'notes' => null])->assertOk();
        $this->putJson($url.'/monthly-budget-rules', ['rules' => [
            ['category_id' => $category->id, 'default_amount' => 70000, 'rollover_policy' => 'expires'],
        ]])->assertOk();

        $this->getJson($url.'?month=2026-08')->assertJsonPath('plan.rows.0.base_budget', 60000)->assertJsonPath('plan.rows.0.is_override', true);
        $this->getJson($url.'?month=2026-09')->assertJsonPath('plan.rows.0.base_budget', 70000)->assertJsonPath('plan.rows.0.is_override', false);
        Carbon::setTestNow();
    }
}
