<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Category extends Model
{
    protected $fillable = ['workspace_id', 'name', 'icon', 'color', 'kind'];
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
