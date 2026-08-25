<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AdminController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_superadmin, 403);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'users' => User::query()->with(['memberships.workspace:id,name'])->orderBy('name')->get(),
            'workspaces' => Workspace::query()->withCount(['members', 'transactions'])->with('categories')->orderBy('name')->get(),
        ]);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'is_superadmin' => ['required', 'boolean'],
        ]);
        if ($user->is($request->user()) && (! $data['is_active'] || ! $data['is_superadmin'])) {
            throw ValidationException::withMessages(['user' => 'No puedes desactivar ni retirar el rol a tu propia cuenta.']);
        }
        $user->update($data);

        return response()->json($user->fresh()->load('memberships.workspace:id,name'));
    }

    public function updateWorkspace(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['household', 'business'])],
            'currency' => ['required', 'string', 'size:3'],
            'archived' => ['required', 'boolean'],
        ]);
        $workspace->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'currency' => strtoupper($data['currency']),
            'archived_at' => $data['archived'] ? ($workspace->archived_at ?? now()) : null,
        ]);

        return response()->json($workspace->fresh()->loadCount(['members', 'transactions'])->load('categories'));
    }

    public function storeCategory(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->categoryData($request);

        return response()->json($workspace->categories()->create($data), 201);
    }

    public function updateCategory(Request $request, Workspace $workspace, Category $category): JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($category->workspace_id === $workspace->id, 404);
        $category->update($this->categoryData($request));

        return response()->json($category);
    }

    public function destroyCategory(Request $request, Workspace $workspace, Category $category): JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($category->workspace_id === $workspace->id, 404);
        if ($category->transactions()->exists()) {
            throw ValidationException::withMessages(['category' => 'No se puede eliminar una categoría que ya tiene movimientos.']);
        }
        $category->delete();

        return response()->json([], 204);
    }

    private function categoryData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(['income', 'expense'])],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
    }
}
