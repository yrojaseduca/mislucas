<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BankConnection;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

final class CsvTransactionImportService
{
    public function import(Workspace $workspace, int $userId, UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        throw_if($handle === false, new RuntimeException('No se pudo leer el archivo CSV.'));
        $firstLine = fgets($handle);
        throw_if($firstLine === false, new RuntimeException('El archivo CSV está vacío.'));
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);
        $headers = array_map($this->normalizeHeader(...), fgetcsv($handle, separator: $delimiter) ?: []);
        $dateIndex = $this->column($headers, ['fecha', 'date', 'fecha_operacion', 'fecha_valor']);
        $descriptionIndex = $this->column($headers, ['descripcion', 'description', 'concepto', 'detalle', 'comercio']);
        $amountIndex = $this->column($headers, ['importe', 'amount', 'cantidad', 'monto']);
        $typeIndex = $this->column($headers, ['tipo', 'type']);
        throw_if($dateIndex === null || $descriptionIndex === null || $amountIndex === null, new RuntimeException('El CSV debe incluir las columnas fecha, descripción/concepto e importe.'));

        $connection = BankConnection::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'provider' => 'csv', 'external_id' => 'csv-inbox'],
            ['user_id' => $userId, 'provider_name' => 'Importación CSV', 'status' => 'active'],
        );
        $account = $connection->accounts()->firstOrCreate(
            ['external_id' => 'csv-inbox'],
            ['account_id' => $workspace->accounts()->value('id'), 'kind' => 'csv', 'display_name' => 'Archivo CSV', 'currency' => $workspace->currency],
        );

        $imported = 0;
        $duplicates = 0;
        $errors = [];
        $line = 1;
        while (($row = fgetcsv($handle, separator: $delimiter)) !== false) {
            $line++;
            if ($row === [null] || count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) continue;
            try {
                $date = $this->date((string) ($row[$dateIndex] ?? ''));
                [$amount, $negative] = $this->amount((string) ($row[$amountIndex] ?? ''));
                $description = trim((string) ($row[$descriptionIndex] ?? ''));
                throw_if($description === '', new RuntimeException('falta la descripción'));
                $rawType = $typeIndex === null ? '' : Str::lower(Str::ascii(trim((string) ($row[$typeIndex] ?? ''))));
                $type = in_array($rawType, ['income', 'ingreso', 'abono'], true) ? 'income' : 'expense';
                if ($rawType === '' && $negative) $type = 'expense';
                $externalId = hash('sha256', implode('|', [$date, $amount, Str::lower($description), implode('|', $row)]));
                $transaction = $account->transactions()->firstOrCreate(['external_id' => $externalId], [
                    'type' => $type, 'amount' => $amount, 'occurred_at' => $date, 'description' => $description, 'status' => 'pending',
                ]);
                $transaction->wasRecentlyCreated ? $imported++ : $duplicates++;
            } catch (\Throwable $exception) {
                $errors[] = "Fila {$line}: {$exception->getMessage()}";
            }
        }
        fclose($handle);

        return compact('imported', 'duplicates', 'errors');
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)->replace("\xEF\xBB\xBF", '')->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    private function column(array $headers, array $names): ?int
    {
        foreach ($names as $name) { $index = array_search($name, $headers, true); if ($index !== false) return $index; }
        return null;
    }

    private function date(string $value): string
    {
        $value = trim($value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'm/d/Y'] as $format) {
            try { $date = CarbonImmutable::createFromFormat('!'.$format, $value); if ($date !== false) return $date->toDateString(); } catch (\Throwable) {}
        }
        throw new RuntimeException('fecha no válida');
    }

    private function amount(string $value): array
    {
        $clean = preg_replace('/[^0-9,\.\-]/u', '', trim($value)) ?? '';
        $negative = str_contains($clean, '-');
        $clean = str_replace('-', '', $clean);
        $comma = strrpos($clean, ','); $dot = strrpos($clean, '.');
        if ($comma !== false && $dot !== false) {
            $decimal = $comma > $dot ? ',' : '.'; $thousands = $decimal === ',' ? '.' : ',';
            $clean = str_replace($thousands, '', $clean); $clean = str_replace($decimal, '.', $clean);
        } elseif ($comma !== false) {
            $clean = strlen($clean) - $comma - 1 <= 2 ? str_replace(',', '.', $clean) : str_replace(',', '', $clean);
        } elseif ($dot !== false && strlen($clean) - $dot - 1 > 2) {
            $clean = str_replace('.', '', $clean);
        }
        throw_if(! is_numeric($clean) || (float) $clean == 0.0, new RuntimeException('importe no válido'));
        return [(int) round(abs((float) $clean) * 100), $negative];
    }
}
