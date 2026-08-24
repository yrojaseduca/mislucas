<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBudgetRequest;
use App\Models\Budget;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

final class BudgetController extends Controller
{
    public function store(StoreBudgetRequest $request, Workspace $workspace): JsonResponse
    {
        $data = $request->validated();
        $data['is_override'] = true;
        $data['month'] = CarbonImmutable::parse($data['month'])->startOfMonth()->toDateString();
        $query = Budget::query()->where('workspace_id', $workspace->id)
            ->where('type', $data['type'])->whereDate('month', $data['month']);
        $data['category_id'] === null ? $query->whereNull('category_id') : $query->where('category_id', $data['category_id']);
        $budget = $query->first();
        $created = $budget === null;
        $budget ??= new Budget(['workspace_id' => $workspace->id]);
        $budget->fill($data)->save();

        return response()->json($budget, $created ? 201 : 200);
    }
}
