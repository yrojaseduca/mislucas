<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BankConnection extends Model
{
    protected $fillable = ['workspace_id', 'user_id', 'provider', 'external_id', 'institution_id', 'provider_name', 'status', 'access_token', 'refresh_token', 'expires_at', 'last_synced_at'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'expires_at' => 'datetime', 'last_synced_at' => 'datetime'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }
}
