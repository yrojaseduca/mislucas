<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\DebtIncrease;
use App\Models\Workspace;
use App\Services\WealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WealthController extends Controller
{
    private function authorizeMember(Request $request, Workspace $workspace): void
    {
        abort_unless($workspace->members()->where('user_id', $request->user()->id)->exists(), 403);
    }

    public function storeDebt(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeMember($request, $workspace);
        $data = $request->validate(['name' => 'required|string|max:255', 'creditor' => 'nullable|string|max:255', 'original_amount' => 'required|integer|min:1', 'outstanding_balance' => 'required|integer|min:0', 'annual_interest_rate' => 'required|numeric|min:0|max:100']);

        return response()->json($workspace->debts()->create($data), 201);
    }

    public function storeInvestment(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeMember($request, $workspace);
        $data = $request->validate(['name' => 'required|string|max:255', 'symbol' => 'nullable|string|max:30', 'type' => 'required|in:stock,fund,crypto,property,other', 'quantity' => 'required|numeric|min:0', 'average_cost' => 'required|integer|min:0', 'current_price' => 'required|integer|min:0', 'currency' => 'required|string|size:3']);

        return response()->json($workspace->investmentPositions()->create($data), 201);
    }

    public function pay(Request $request, Workspace $workspace, Debt $debt, WealthService $service): JsonResponse
    {
        $this->authorizeMember($request, $workspace);
        abort_unless($debt->workspace_id === $workspace->id, 404);
        $data = $request->validate(['amount' => 'required|integer|min:1', 'interest_amount' => 'nullable|integer|min:0', 'occurred_at' => 'required|date', 'account_id' => 'nullable|integer', 'category_id' => 'nullable|integer', 'paid_by_member_id' => 'nullable|integer', 'description' => 'nullable|string|max:255', 'notes' => 'nullable|string', 'splits' => 'present|array', 'splits.*.member_id' => 'required|integer', 'splits.*.amount' => 'required|integer|min:0', 'splits.*.percentage' => 'nullable|numeric']);

        return response()->json($service->pay($debt, $data, (int) $request->user()->id), 201);
    }

    public function increase(Request $request, Workspace $workspace, Debt $debt, WealthService $service): JsonResponse
    {
        $this->authorizeMember($request, $workspace);
        abort_unless($debt->workspace_id === $workspace->id, 404);
        $data = $request->validate([
            'amount' => 'required|integer|min:1',
            'occurred_at' => 'required|date',
            'description' => 'nullable|string|max:255',
        ]);

        return response()->json($service->increase($debt, $data), 201);
    }

    public function destroyIncrease(Request $request, Workspace $workspace, Debt $debt, DebtIncrease $increase, WealthService $service): JsonResponse
    {
        $this->authorizeMember($request, $workspace);
        abort_unless($debt->workspace_id === $workspace->id && $increase->debt_id === $debt->id, 404);
        $service->removeIncrease($increase);

        return response()->json(null, 204);
    }
}
