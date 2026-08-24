<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Debt;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransactionService
{
    public function __construct(
        private TransactionRepository $transactions,
        private RecurringTransactionService $recurringTransactions,
        private WealthService $wealth,
    ) {}

    public function create(Workspace $workspace, array $data, int $userId): Transaction
    {
        $this->validateSplits($workspace, $data);
        $recurrence = $data['recurrence'] ?? null;
        $debtPayment = $data['debt_payment'] ?? null;
        if ($recurrence && $debtPayment) {
            throw ValidationException::withMessages(['debt_payment' => 'Un pago de deuda no puede ser recurrente todavía.']);
        }
        $transaction = collect($data)->except(['splits', 'recurrence', 'debt_payment'])->all();

        return DB::transaction(function () use ($transaction, $workspace, $userId, $data, $recurrence, $debtPayment): Transaction {
            $attributes = [...$transaction, 'workspace_id' => $workspace->id, 'created_by_user_id' => $userId];
            $movement = $this->transactions->create($attributes, $data['splits']);
            if ($recurrence) {
                $rule = $this->recurringTransactions->createRule($attributes, $data['splits'], $recurrence);
                $movement->update(['recurring_transaction_id' => $rule->id]);
            }
            if ($debtPayment) {
                $this->wealth->applyToMovement(Debt::query()->lockForUpdate()->findOrFail($debtPayment['debt_id']), $movement, (int) $debtPayment['interest_amount']);
            }

            return $movement->load('debtPayment.debt');
        });
    }

    public function update(Workspace $workspace, Transaction $movement, array $data): Transaction
    {
        $this->validateSplits($workspace, $data);
        $attributes = collect($data)->except(['splits', 'recurrence', 'debt_payment'])->all();

        return DB::transaction(function () use ($movement, $attributes, $data): Transaction {
            if ($movement->debtPayment) {
                $this->wealth->reverse($movement->debtPayment);
            }
            $updated = $this->transactions->update($movement, $attributes, $data['splits']);
            if ($data['debt_payment'] ?? null) {
                $this->wealth->applyToMovement(Debt::query()->lockForUpdate()->findOrFail($data['debt_payment']['debt_id']), $updated, (int) $data['debt_payment']['interest_amount']);
            }

            return $updated->load('debtPayment.debt');
        });
    }

    public function delete(Transaction $movement): void
    {
        DB::transaction(function () use ($movement): void {
            if ($movement->debtPayment) {
                $this->wealth->reverse($movement->debtPayment);
            } $this->transactions->delete($movement);
        });
    }

    private function validateSplits(Workspace $workspace, array $data): void
    {
        if ($data['type'] === 'expense' && array_sum(array_column($data['splits'], 'amount')) !== $data['amount']) {
            throw ValidationException::withMessages(['splits' => 'El total del reparto debe coincidir con el importe.']);
        }
        $memberIds = $workspace->members()->pluck('id')->all();
        foreach ($data['splits'] as $split) {
            if (! in_array($split['member_id'], $memberIds, true)) {
                throw ValidationException::withMessages(['splits' => 'Todos los participantes deben pertenecer al espacio.']);
            }
        }
    }
}
