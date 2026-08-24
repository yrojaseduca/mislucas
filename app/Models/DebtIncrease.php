<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DebtIncrease extends Model
{
    protected $fillable = ['debt_id', 'amount', 'occurred_at', 'description'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'occurred_at' => 'date'];
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }
}
