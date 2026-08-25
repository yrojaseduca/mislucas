<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankingController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MonthlyBudgetRuleController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WealthController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\Api\WorkspaceInvitationController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
    Route::get('/invitations/{token}', [WorkspaceInvitationController::class, 'show']);
    Route::post('/invitations/{token}/accept', [WorkspaceInvitationController::class, 'accept']);

    Route::middleware('auth')->group(function (): void {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/workspaces', [WorkspaceController::class, 'index']);
        Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'show']);
        Route::post('/workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'store']);
        Route::post('/workspaces/{workspace}/transactions', [TransactionController::class, 'store']);
        Route::put('/workspaces/{workspace}/transactions/{transaction}', [TransactionController::class, 'update']);
        Route::delete('/workspaces/{workspace}/transactions/{transaction}', [TransactionController::class, 'destroy']);
        Route::post('/workspaces/{workspace}/budgets', [BudgetController::class, 'store']);
        Route::post('/workspaces/{workspace}/categories', [CategoryController::class, 'store']);
        Route::put('/workspaces/{workspace}/monthly-budget-rules', [MonthlyBudgetRuleController::class, 'store']);
        Route::post('/workspaces/{workspace}/debts', [WealthController::class, 'storeDebt']);
        Route::post('/workspaces/{workspace}/investments', [WealthController::class, 'storeInvestment']);
        Route::post('/workspaces/{workspace}/debts/{debt}/payments', [WealthController::class, 'pay']);
        Route::post('/workspaces/{workspace}/debts/{debt}/increases', [WealthController::class, 'increase']);
        Route::delete('/workspaces/{workspace}/debts/{debt}/increases/{increase}', [WealthController::class, 'destroyIncrease']);
        Route::get('/workspaces/{workspace}/bank/institutions', [BankingController::class, 'institutions']);
        Route::post('/workspaces/{workspace}/bank/connect', [BankingController::class, 'connect']);
        Route::post('/workspaces/{workspace}/bank/connections/{connection}/sync', [BankingController::class, 'sync']);
        Route::post('/workspaces/{workspace}/bank-transactions/{bankTransaction}/accept', [BankingController::class, 'accept']);
        Route::delete('/workspaces/{workspace}/bank-transactions/{bankTransaction}', [BankingController::class, 'dismiss']);
        Route::get('/bank/callback', [BankingController::class, 'callback']);
    });
});

Route::view('/{path?}', 'app')->where('path', '.*');
