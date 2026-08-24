<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class InvestmentPosition extends Model
{
    protected $fillable = ['workspace_id', 'name', 'symbol', 'type', 'quantity', 'average_cost', 'current_price', 'currency'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:8', 'average_cost' => 'integer', 'current_price' => 'integer'];
    }
}
