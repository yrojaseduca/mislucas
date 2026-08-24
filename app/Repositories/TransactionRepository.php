<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class TransactionRepository
{
    public function recentForWorkspace(int $workspaceId, int $limit = 10, ?CarbonInterface $start = null, ?CarbonInterface $end = null): Collection
    {
        return Transaction::query()->with(['category', 'payer', 'account', 'splits.member', 'debtPayment.debt'])
            ->where('workspace_id', $workspaceId)
            ->when($start && $end, fn ($query) => $query->whereBetween('occurred_at', [$start, $end]))
            ->latest('occurred_at')->latest('id')->limit($limit)->get();
    }

    public function create(array $transaction, array $splits): Transaction
    {
        $model = Transaction::query()->create($transaction);
        $model->splits()->createMany($splits);

        return $model->load(['category', 'payer', 'account', 'splits.member', 'debtPayment.debt']);
    }

    public function update(Transaction $model, array $transaction, array $splits): Transaction
    {
        $model->update($transaction);
        $model->splits()->delete();
        $model->splits()->createMany($splits);

        return $model->load(['category', 'payer', 'account', 'splits.member', 'debtPayment.debt']);
    }

    public function delete(Transaction $model): void
    {
        $model->delete();
    }
}
