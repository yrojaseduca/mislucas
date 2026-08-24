<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BankTransaction;
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
        $workspace->load(['members', 'accounts', 'categories', 'debts.increases', 'investmentPositions', 'bankConnections.accounts']);
        $all = $workspace->transactions()->with('splits')->whereBetween('occurred_at', [$start, $end])->get();
        $income = (int) $all->where('type', 'income')->sum('amount');
        $expenses = (int) $all->where('type', 'expense')->sum('amount');
        $balances = $workspace->members->map(function ($member) use ($all): array {
            $paid = (int) $all->where('type', 'expense')->where('paid_by_member_id', $member->id)->sum('amount');
            $share = (int) $all->flatMap->splits->where('member_id', $member->id)->sum('amount');

            return ['member' => $member, 'paid' => $paid, 'share' => $share, 'balance' => $paid - $share];
        });

        $bankInbox = BankTransaction::query()
            ->with('bankAccount')
            ->where('status', 'pending')
            ->whereHas('bankAccount.connection', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->latest('occurred_at')
            ->get();

        return ['workspace' => $workspace, 'period' => $start->format('Y-m'), 'summary' => ['income' => $income, 'expenses' => $expenses, 'result' => $income - $expenses], 'balances' => $balances, 'transactions' => $this->transactions->recentForWorkspace($workspace->id, 50, $start, $end), 'plan' => $this->budgetPlan->build($workspace, $month), 'bank_inbox' => $bankInbox, 'banking_configured' => (bool) config('services.gocardless.secret_id')];
    }
}
