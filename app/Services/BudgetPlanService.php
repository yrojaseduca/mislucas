<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Budget;
use App\Models\RecurringTransaction;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class BudgetPlanService
{
    public function build(Workspace $workspace, ?string $month = null): array
    {
        $start = CarbonImmutable::parse($month ?? today())->startOfMonth();
        $end = $start->endOfMonth();
        $this->materializeMonth($workspace, $start);
        $budgets = $workspace->budgets()->with('category')->whereDate('month', $start)->get();
        $transactions = $workspace->transactions()->whereBetween('occurred_at', [$start, $end])->get();
        $committed = $this->committedByKey($workspace, $start, $end);
        $previousBudgets = $workspace->budgets()->whereDate('month', $start->subMonthNoOverflow()->startOfMonth())->get();
        $previousTransactions = $workspace->transactions()->whereBetween('occurred_at', [$start->subMonthNoOverflow()->startOfMonth(), $start->subMonthNoOverflow()->endOfMonth()])->get();

        $keys = collect($budgets->map(fn ($budget): string => $this->key($budget->type, $budget->category_id)))
            ->merge($transactions->map(fn ($movement): string => $this->key($movement->type, $movement->category_id)))
            ->merge($committed->keys())->unique();

        $rows = $keys->map(function (string $key) use ($budgets, $transactions, $committed, $previousBudgets, $previousTransactions, $workspace): array {
            [$type, $categoryId] = explode(':', $key);
            $categoryId = $categoryId === 'general' ? null : (int) $categoryId;
            $budget = $budgets->first(fn ($item): bool => $item->type === $type && $item->category_id === $categoryId);
            $actual = (int) $transactions->where('type', $type)->where('category_id', $categoryId)->sum('amount');
            $pending = (int) ($committed[$key] ?? 0);
            $carry = $this->carryFor($previousBudgets, $previousTransactions, $type, $categoryId);
            $planned = (int) ($budget?->amount ?? 0) + $carry;
            $category = $workspace->categories->firstWhere('id', $categoryId);

            return [
                'key' => $key, 'type' => $type, 'category_id' => $categoryId,
                'name' => $budget?->name ?? $category?->name ?? ($type === 'income' ? 'Otros ingresos' : 'Sin categoría'),
                'budget_id' => $budget?->id, 'budget' => $planned, 'base_budget' => (int) ($budget?->amount ?? 0),
                'is_override' => (bool) ($budget?->is_override ?? false),
                'carry' => $carry, 'committed' => $pending, 'actual' => $actual,
                'forecast' => max($planned, $actual + $pending), 'remaining' => $planned - $actual - $pending,
                'rollover_policy' => $budget?->rollover_policy ?? 'expires',
            ];
        })->sortBy([['type', 'desc'], ['name', 'asc']])->values();

        return [
            'month' => $start->format('Y-m'),
            'base_rules' => $workspace->monthlyBudgetRules()->with('category')->where('is_active', true)->get(),
            'rows' => $rows,
            'summary' => [
                'expected_income' => (int) $rows->where('type', 'income')->sum('forecast'),
                'expected_expenses' => (int) $rows->where('type', 'expense')->sum('forecast'),
                'actual_income' => (int) $rows->where('type', 'income')->sum('actual'),
                'actual_expenses' => (int) $rows->where('type', 'expense')->sum('actual'),
            ],
        ];
    }

    public function materializeMonth(Workspace $workspace, CarbonImmutable $month): int
    {
        $start = $month->startOfMonth();
        $created = 0;
        $workspace->monthlyBudgetRules()->with('category')->where('is_active', true)
            ->whereDate('starts_on', '<=', $start->endOfMonth())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start))
            ->get()->each(function ($rule) use ($workspace, $start, &$created): void {
                $budget = Budget::query()->where('workspace_id', $workspace->id)->where('category_id', $rule->category_id)
                    ->whereDate('month', $start)->first();
                if ($budget) {
                    return;
                }
                Budget::query()->create([
                    'workspace_id' => $workspace->id,
                    'category_id' => $rule->category_id,
                    'month' => $start->toDateString(),
                    'monthly_budget_rule_id' => $rule->id,
                    'type' => $rule->category->kind,
                    'name' => $rule->category->name,
                    'amount' => $rule->default_amount,
                    'is_override' => false,
                    'rollover_policy' => $rule->rollover_policy,
                ]);
                $created++;
            });

        return $created;
    }

    private function committedByKey(Workspace $workspace, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $totals = collect();
        RecurringTransaction::query()->where('workspace_id', $workspace->id)->where('is_active', true)
            ->whereDate('next_run_on', '<=', $end)->get()->each(function ($rule) use ($start, $end, $totals): void {
                $date = CarbonImmutable::parse($rule->next_run_on);
                while ($date->lte($end) && (! $rule->ends_on || $date->lte($rule->ends_on))) {
                    if ($date->gte($start)) {
                        $key = $this->key($rule->type, $rule->category_id);
                        $totals[$key] = (int) ($totals[$key] ?? 0) + $rule->amount;
                    }
                    $date = match ($rule->frequency) {
                        'weekly' => $date->addWeek(), 'monthly' => $date->addMonthNoOverflow(), 'yearly' => $date->addYearNoOverflow(),
                    };
                }
            });

        return $totals;
    }

    private function carryFor(Collection $budgets, Collection $transactions, string $type, ?int $categoryId): int
    {
        $previous = $budgets->first(fn ($item): bool => $item->type === $type && $item->category_id === $categoryId);
        if (! $previous || $previous->rollover_policy !== 'carry') {
            return 0;
        }
        $actual = (int) $transactions->where('type', $type)->where('category_id', $categoryId)->sum('amount');

        return max(0, $previous->amount - $actual);
    }

    private function key(string $type, ?int $categoryId): string
    {
        return $type.':'.($categoryId ?? 'general');
    }
}
