<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Debt extends Model
{
    protected $fillable = ['workspace_id', 'name', 'creditor', 'original_amount', 'outstanding_balance', 'annual_interest_rate', 'is_active'];

    protected function casts(): array
    {
        return ['original_amount' => 'integer', 'outstanding_balance' => 'integer', 'annual_interest_rate' => 'decimal:4', 'is_active' => 'boolean'];
    }

    public function increases(): HasMany
    {
        return $this->hasMany(DebtIncrease::class)->latest('occurred_at')->latest('id');
    }
}
