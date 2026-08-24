<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Debt;
use App\Models\DebtIncrease;
use App\Models\DebtPayment;
use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\DB;

final readonly class WealthService
{
    public function __construct(private TransactionRepository $transactions) {}

    public function pay(Debt $debt, array $data, int $userId): Transaction
    {
        return DB::transaction(function () use ($debt, $data, $userId): Transaction {
            $interest = isset($data['interest_amount']) ? min((int) $data['amount'], (int) $data['interest_amount']) : min((int) $data['amount'], (int) round($debt->outstanding_balance * (float) $debt->annual_interest_rate / 1200));
            $principal = min($debt->outstanding_balance, $data['amount'] - $interest);
            $movement = $this->transactions->create(['workspace_id' => $debt->workspace_id, 'account_id' => $data['account_id'], 'category_id' => $data['category_id'], 'paid_by_member_id' => $data['paid_by_member_id'], 'created_by_user_id' => $userId, 'type' => 'expense', 'amount' => $data['amount'], 'occurred_at' => $data['occurred_at'], 'description' => $data['description'] ?? 'Pago '.$debt->name, 'notes' => $data['notes'] ?? null], $data['splits']);
            $this->applyToMovement($debt, $movement, $interest);

            return $movement;
        });
    }

    public function increase(Debt $debt, array $data): DebtIncrease
    {
        return DB::transaction(function () use ($debt, $data): DebtIncrease {
            $lockedDebt = Debt::query()->lockForUpdate()->findOrFail($debt->id);
            $increase = $lockedDebt->increases()->create($data);
            $lockedDebt->update([
                'original_amount' => $lockedDebt->original_amount + $increase->amount,
                'outstanding_balance' => $lockedDebt->outstanding_balance + $increase->amount,
                'is_active' => true,
            ]);

            return $increase;
        });
    }

    public function removeIncrease(DebtIncrease $increase): void
    {
        DB::transaction(function () use ($increase): void {
            $lockedIncrease = DebtIncrease::query()->lockForUpdate()->findOrFail($increase->id);
            $debt = Debt::query()->lockForUpdate()->findOrFail($lockedIncrease->debt_id);

            abort_if($debt->outstanding_balance < $lockedIncrease->amount, 422, 'No se puede eliminar la ampliación porque parte de ese capital ya ha sido amortizado.');

            $balance = $debt->outstanding_balance - $lockedIncrease->amount;
            $debt->update([
                'original_amount' => $debt->original_amount - $lockedIncrease->amount,
                'outstanding_balance' => $balance,
                'is_active' => $balance > 0,
            ]);
            $lockedIncrease->delete();
        });
    }

    public function applyToMovement(Debt $debt, Transaction $movement, int $interest): DebtPayment
    {
        $interest = min($movement->amount, $interest);
        $principal = min($debt->outstanding_balance, $movement->amount - $interest);
        $payment = DebtPayment::query()->create(['debt_id' => $debt->id, 'transaction_id' => $movement->id, 'total_amount' => $movement->amount, 'principal_amount' => $principal, 'interest_amount' => $interest]);
        $balance = max(0, $debt->outstanding_balance - $principal);
        $debt->update(['outstanding_balance' => $balance, 'is_active' => $balance > 0]);

        return $payment;
    }

    public function reverse(DebtPayment $payment): void
    {
        $debt = Debt::query()->lockForUpdate()->findOrFail($payment->debt_id);
        $debt->update(['outstanding_balance' => min($debt->original_amount, $debt->outstanding_balance + $payment->principal_amount), 'is_active' => true]);
        $payment->delete();
    }
}
