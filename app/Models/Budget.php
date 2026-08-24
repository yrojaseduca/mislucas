<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Budget extends Model
{
    protected $fillable = ['workspace_id', 'monthly_budget_rule_id', 'category_id', 'type', 'name', 'month', 'amount', 'is_override', 'rollover_policy', 'notes'];

    protected function casts(): array
    {
        return ['month' => 'date', 'amount' => 'integer', 'is_override' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
