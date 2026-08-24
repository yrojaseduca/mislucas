<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\WorkspaceDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Workspace::query()
            ->with('members')
            ->whereHas('members', fn ($query) => $query->where('user_id', $request->user()->id))
            ->get());
    }

    public function show(Request $request, Workspace $workspace, WorkspaceDashboardService $dashboard): JsonResponse
    {
        abort_unless($workspace->members()->where('user_id', $request->user()->id)->exists(), 403);

        return response()->json($dashboard->build($workspace, $request->query('month')));
    }
}
