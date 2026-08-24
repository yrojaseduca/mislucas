<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\BankAccount;
use App\Models\BankTransaction;

final class BankTransactionRepository
{
    public function import(BankAccount $account, array $attributes): BankTransaction
    {
        return BankTransaction::query()->updateOrCreate(
            ['bank_account_id' => $account->id, 'external_id' => $attributes['external_id']],
            $attributes,
        );
    }

    public function dismiss(BankTransaction $transaction): void
    {
        $transaction->update(['status' => 'dismissed']);
    }
}
