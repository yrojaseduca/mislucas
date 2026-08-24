<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransactionSplit extends Model
{
    protected $fillable = ['transaction_id', 'member_id', 'amount', 'percentage'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'percentage' => 'decimal:4'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'member_id');
    }
}
