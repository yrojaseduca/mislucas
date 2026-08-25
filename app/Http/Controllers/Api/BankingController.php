<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\WorkspaceMemberRequest;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Workspace;
use App\Services\BankIntegrationService;
use App\Services\CsvTransactionImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class BankingController extends Controller
{
    public function importCsv(WorkspaceMemberRequest $request, Workspace $workspace, CsvTransactionImportService $service): JsonResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        try {
            return response()->json($service->import($workspace, (int) $request->user()->id, $data['file']));
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }
    }

    public function institutions(WorkspaceMemberRequest $request, Workspace $workspace, BankIntegrationService $service): JsonResponse
    {
        return response()->json($service->institutions());
    }

    public function connect(WorkspaceMemberRequest $request, Workspace $workspace, BankIntegrationService $service): JsonResponse
    {
        $data = $request->validate(['institution_id' => 'required|string|max:255']);

        return response()->json(['authorization_url' => $service->authorizationUrl($workspace, $request->user(), $data['institution_id'])]);
    }

    public function callback(Request $request, BankIntegrationService $service): RedirectResponse
    {
        $data = $request->validate(['code' => 'required|string', 'state' => 'required|string']);
        $service->complete($data['code'], $data['state'], $request->user());

        return redirect('/?bank=connected');
    }

    public function sync(WorkspaceMemberRequest $request, Workspace $workspace, BankConnection $connection, BankIntegrationService $service): JsonResponse
    {
        abort_unless($connection->workspace_id === $workspace->id, 404);

        return response()->json(['imported' => $service->sync($connection)]);
    }

    public function accept(StoreTransactionRequest $request, Workspace $workspace, BankTransaction $bankTransaction, BankIntegrationService $service): JsonResponse
    {
        abort_unless($bankTransaction->bankAccount()->whereHas('connection', fn ($query) => $query->where('workspace_id', $workspace->id))->exists(), 404);

        return response()->json($service->accept($bankTransaction, $workspace, $request->validated(), (int) $request->user()->id), 201);
    }

    public function dismiss(WorkspaceMemberRequest $request, Workspace $workspace, BankTransaction $bankTransaction, BankIntegrationService $service): JsonResponse
    {
        abort_unless($bankTransaction->bankAccount()->whereHas('connection', fn ($query) => $query->where('workspace_id', $workspace->id))->exists(), 404);
        $service->dismiss($bankTransaction);

        return response()->json(status: 204);
    }
}
