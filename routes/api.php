<?php

declare(strict_types=1);
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/workspaces', [WorkspaceController::class, 'index']);
Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'show']);
Route::post('/workspaces/{workspace}/transactions', [TransactionController::class, 'store']);
