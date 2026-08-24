<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RecurringTransaction;
use App\Repositories\TransactionRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class RecurringTransactionService
{
    public function __construct(private TransactionRepository $transactions) {}

    public function createRule(array $transaction, array $splits, array $recurrence): RecurringTransaction
    {
        return RecurringTransaction::query()->create([
            ...collect($transaction)->except('occurred_at')->all(),
            'splits' => $splits,
            'frequency' => $recurrence['frequency'],
            'next_run_on' => $this->nextDate(CarbonImmutable::parse($transaction['occurred_at']), $recurrence['frequency'])->toDateString(),
            'ends_on' => $recurrence['ends_on'] ?? null,
        ]);
    }

    public function processDue(): int
    {
        $processed = 0;
        RecurringTransaction::query()->where('is_active', true)->whereDate('next_run_on', '<=', today())->pluck('id')
            ->each(function (int $id) use (&$processed): void {
                DB::transaction(function () use ($id, &$processed): void {
                    $rule = RecurringTransaction::query()->lockForUpdate()->find($id);
                    if (! $rule || ! $rule->is_active || $rule->next_run_on->isAfter(today())) {
                        return;
                    }
                    $runDate = $rule->next_run_on->toDateString();
                    $this->transactions->create([
                        'recurring_transaction_id' => $rule->id,
                        'workspace_id' => $rule->workspace_id,
                        'account_id' => $rule->account_id,
                        'category_id' => $rule->category_id,
                        'paid_by_member_id' => $rule->paid_by_member_id,
                        'created_by_user_id' => $rule->created_by_user_id,
                        'type' => $rule->type,
                        'amount' => $rule->amount,
                        'occurred_at' => $runDate,
                        'description' => $rule->description,
                        'notes' => $rule->notes,
                    ], $rule->splits);
                    $next = $this->nextDate(CarbonImmutable::parse($runDate), $rule->frequency);
                    $rule->update(['next_run_on' => $next->toDateString(), 'is_active' => ! $rule->ends_on || $next->lte($rule->ends_on)]);
                    $processed++;
                });
            });

        return $processed;
    }

    private function nextDate(CarbonImmutable $date, string $frequency): CarbonImmutable
    {
        return match ($frequency) {
            'weekly' => $date->addWeek(),
            'monthly' => $date->addMonthNoOverflow(),
            'yearly' => $date->addYearNoOverflow(),
        };
    }
}
