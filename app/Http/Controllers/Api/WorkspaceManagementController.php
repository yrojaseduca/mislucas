<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class WorkspaceManagementController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_superadmin, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['household', 'business'])],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $workspace = DB::transaction(function () use ($data, $request): Workspace {
            $workspace = Workspace::query()->create([
                ...$data,
                'currency' => strtoupper($data['currency']),
            ]);
            $workspace->members()->create([
                'user_id' => $request->user()->id,
                'display_name' => $request->user()->name,
                'role' => 'owner',
            ]);
            $workspace->accounts()->create(['name' => 'Cuenta principal', 'type' => 'bank']);
            $workspace->categories()->createMany([
                ['name' => 'Ingresos', 'kind' => 'income', 'icon' => 'pi-wallet'],
                ['name' => 'Vivienda', 'kind' => 'expense', 'icon' => 'pi-home'],
                ['name' => 'Alimentación', 'kind' => 'expense', 'icon' => 'pi-shopping-cart'],
                ['name' => 'Transporte', 'kind' => 'expense', 'icon' => 'pi-car'],
                ['name' => 'Otros', 'kind' => 'expense', 'icon' => 'pi-tag'],
            ]);

            return $workspace->load('members');
        });

        return response()->json($workspace, 201);
    }
}
