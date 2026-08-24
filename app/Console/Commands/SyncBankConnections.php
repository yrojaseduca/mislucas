<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BankConnection;
use App\Services\BankIntegrationService;
use Illuminate\Console\Command;
use Throwable;

final class SyncBankConnections extends Command
{
    protected $signature = 'bank:sync';

    protected $description = 'Importa nuevas operaciones de las conexiones bancarias activas';

    public function handle(BankIntegrationService $service): int
    {
        BankConnection::query()->where('status', 'active')->eachById(function (BankConnection $connection) use ($service): void {
            try {
                $service->sync($connection);
            } catch (Throwable $exception) {
                report($exception);
                $this->warn("No se pudo sincronizar la conexión {$connection->id}.");
            }
        });

        return self::SUCCESS;
    }
}
