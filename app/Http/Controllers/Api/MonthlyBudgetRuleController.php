<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMonthlyBudgetRulesRequest;
use App\Models\Budget;
use App\Models\MonthlyBudgetRule;
use App\Models\Workspace;
use App\Services\BudgetPlanService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class MonthlyBudgetRuleController extends Controller
{
    public function store(StoreMonthlyBudgetRulesRequest $request, Workspace $workspace, BudgetPlanService $plans): JsonResponse
    {
        $month = CarbonImmutable::now()->startOfMonth();
        $rules = collect($request->validated('rules'));

        DB::transaction(function () use ($workspace, $rules, $month, $plans): void {
            $categoryIds = $rules->pluck('category_id');
            $removed = $workspace->monthlyBudgetRules()->whereNotIn('category_id', $categoryIds)->pluck('id');
            $workspace->monthlyBudgetRules()->whereIn('id', $removed)->update(['is_active' => false, 'ends_on' => $month->subDay()]);
            Budget::query()->whereIn('monthly_budget_rule_id', $removed)->where('is_override', false)->whereDate('month', '>', $month)->delete();

            $rules->each(function (array $data) use ($workspace, $month): void {
                $rule = MonthlyBudgetRule::query()->updateOrCreate(
                    ['workspace_id' => $workspace->id, 'category_id' => $data['category_id']],
                    [...$data, 'starts_on' => $month, 'ends_on' => null, 'is_active' => true],
                );
                Budget::query()->where('monthly_budget_rule_id', $rule->id)->where('is_override', false)
                    ->whereDate('month', '>', $month)->update(['amount' => $rule->default_amount, 'rollover_policy' => $rule->rollover_policy]);
            });

            $plans->materializeMonth($workspace, $month);
        });

        return response()->json($workspace->monthlyBudgetRules()->with('category')->where('is_active', true)->get());
    }
}
