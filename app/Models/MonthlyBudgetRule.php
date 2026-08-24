<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MonthlyBudgetRule extends Model
{
    protected $fillable = ['workspace_id', 'category_id', 'default_amount', 'rollover_policy', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return ['default_amount' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
