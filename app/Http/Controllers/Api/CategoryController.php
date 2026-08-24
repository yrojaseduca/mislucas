<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

final class CategoryController extends Controller
{
    public function store(StoreCategoryRequest $request, Workspace $workspace): JsonResponse
    {
        $category = $workspace->categories()->create($request->validated());

        return response()->json($category, 201);
    }
}
