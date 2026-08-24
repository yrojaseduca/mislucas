<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DebtPayment extends Model
{
    protected $fillable = ['debt_id', 'transaction_id', 'total_amount', 'principal_amount', 'interest_amount'];

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }
}
