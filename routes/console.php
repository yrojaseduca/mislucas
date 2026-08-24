<?php

declare(strict_types=1);

use App\Models\Workspace;
use App\Services\BudgetPlanService;
use App\Services\RecurringTransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('recurring:process', function (RecurringTransactionService $service): void {
    $this->info($service->processDue().' movimientos recurrentes generados.');
})->purpose('Generate due recurring financial movements');

Schedule::command('recurring:process')->dailyAt('00:05')->withoutOverlapping();

Artisan::command('budgets:prepare', function (BudgetPlanService $service): void {
    $created = 0;
    Workspace::query()->each(function (Workspace $workspace) use ($service, &$created): void {
        $created += $service->materializeMonth($workspace, CarbonImmutable::now());
    });
    $this->info($created.' presupuestos mensuales preparados.');
})->purpose('Create monthly budget snapshots from active base rules');

Schedule::command('budgets:prepare')->monthlyOn(1, '00:01')->withoutOverlapping();
