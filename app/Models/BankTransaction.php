<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BankTransaction extends Model
{
    protected $fillable = ['bank_account_id', 'transaction_id', 'external_id', 'type', 'amount', 'occurred_at', 'description', 'merchant_name', 'classification', 'status'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'occurred_at' => 'datetime', 'classification' => 'array'];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
