<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class RecurringTransaction extends Model
{
    protected $fillable = [
        'workspace_id', 'account_id', 'category_id', 'paid_by_member_id', 'created_by_user_id',
        'type', 'amount', 'description', 'notes', 'splits', 'frequency', 'next_run_on', 'ends_on', 'is_active',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'splits' => 'array', 'next_run_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean'];
    }
}
