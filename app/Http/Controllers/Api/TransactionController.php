<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\WorkspaceMemberRequest;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;

final class TransactionController extends Controller
{
    public function store(StoreTransactionRequest $request, Workspace $workspace, TransactionService $service): JsonResponse
    {
        $userId = (int) $request->user()->id;

        return response()->json($service->create($workspace, $request->validated(), $userId), 201);
    }

    public function update(StoreTransactionRequest $request, Workspace $workspace, Transaction $transaction, TransactionService $service): JsonResponse
    {
        abort_unless($transaction->workspace_id === $workspace->id, 404);

        return response()->json($service->update($workspace, $transaction, $request->validated()));
    }

    public function destroy(WorkspaceMemberRequest $request, Workspace $workspace, Transaction $transaction, TransactionService $service): JsonResponse
    {
        abort_unless($transaction->workspace_id === $workspace->id, 404);
        $service->delete($transaction);

        return response()->json(status: 204);
    }
}
