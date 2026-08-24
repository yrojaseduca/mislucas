<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Transaction extends Model
{
    protected $fillable = ['recurring_transaction_id', 'workspace_id', 'account_id', 'category_id', 'paid_by_member_id', 'created_by_user_id', 'type', 'amount', 'occurred_at', 'description', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'occurred_at' => 'date'];
    }

    public function splits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'paid_by_member_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function debtPayment(): HasOne
    {
        return $this->hasOne(DebtPayment::class);
    }
}
