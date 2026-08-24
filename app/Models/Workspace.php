<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Workspace extends Model
{
    protected $fillable = ['name', 'type', 'currency'];

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function monthlyBudgetRules(): HasMany
    {
        return $this->hasMany(MonthlyBudgetRule::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function investmentPositions(): HasMany
    {
        return $this->hasMany(InvestmentPosition::class);
    }

    public function bankConnections(): HasMany
    {
        return $this->hasMany(BankConnection::class);
    }
}
