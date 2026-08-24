<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Workspace;
use App\Repositories\TransactionRepository;
use Carbon\CarbonImmutable;

final readonly class WorkspaceDashboardService
{
    public function __construct(private TransactionRepository $transactions, private BudgetPlanService $budgetPlan) {}

    public function build(Workspace $workspace, ?string $month = null): array
    {
        $start = CarbonImmutable::parse($month ?? today())->startOfMonth();
        $end = $start->endOfMonth();
        $workspace->load(['members', 'accounts', 'categories', 'debts.increases', 'investmentPositions']);
        $all = $workspace->transactions()->with('splits')->whereBetween('occurred_at', [$start, $end])->get();
        $income = (int) $all->where('type', 'income')->sum('amount');
        $expenses = (int) $all->where('type', 'expense')->sum('amount');
        $balances = $workspace->members->map(function ($member) use ($all): array {
            $paid = (int) $all->where('type', 'expense')->where('paid_by_member_id', $member->id)->sum('amount');
            $share = (int) $all->flatMap->splits->where('member_id', $member->id)->sum('amount');

            return ['member' => $member, 'paid' => $paid, 'share' => $share, 'balance' => $paid - $share];
        });

        return ['workspace' => $workspace, 'period' => $start->format('Y-m'), 'summary' => ['income' => $income, 'expenses' => $expenses, 'result' => $income - $expenses], 'balances' => $balances, 'transactions' => $this->transactions->recentForWorkspace($workspace->id, 50, $start, $end), 'plan' => $this->budgetPlan->build($workspace, $month)];
    }
}
