<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Account extends Model
{
    protected $fillable = ['workspace_id', 'owner_member_id', 'name', 'type', 'opening_balance', 'is_shared'];

    protected function casts(): array
    {
        return ['opening_balance' => 'integer', 'is_shared' => 'boolean'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
