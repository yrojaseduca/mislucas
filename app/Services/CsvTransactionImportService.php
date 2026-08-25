<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BankConnection;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

final class CsvTransactionImportService
{
    public function import(Workspace $workspace, int $userId, UploadedFile $file): array
    {
        $rows = $this->rows($file);
        throw_if($rows === [], new RuntimeException('El archivo está vacío.'));
        $headerRow = null;
        $headers = [];
        foreach (array_slice($rows, 0, 20, true) as $index => $candidate) {
            $normalized = array_map(fn ($value) => $this->normalizeHeader((string) $value), $candidate);
            if ($this->column($normalized, ['fecha', 'date', 'fecha_operacion', 'fecha_valor']) !== null
                && $this->column($normalized, ['descripcion', 'description', 'concepto', 'detalle', 'comercio']) !== null
                && $this->column($normalized, ['importe', 'amount', 'cantidad', 'monto']) !== null) {
                $headerRow = $index; $headers = $normalized; break;
            }
        }
        throw_if($headerRow === null, new RuntimeException('No se encontró una fila con las columnas fecha, descripción/concepto e importe.'));
        $dateIndex = $this->column($headers, ['fecha', 'date', 'fecha_operacion', 'fecha_valor']);
        $descriptionIndex = $this->column($headers, ['descripcion', 'description', 'concepto', 'detalle', 'comercio']);
        $amountIndex = $this->column($headers, ['importe', 'amount', 'cantidad', 'monto']);
        $typeIndex = $this->column($headers, ['tipo', 'type']);

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
        foreach (array_slice($rows, $headerRow + 1, null, true) as $rowIndex => $row) {
            $line = $rowIndex + 1;
            if ($row === [null] || count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) continue;
            try {
                $date = $this->date($row[$dateIndex] ?? '');
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

        return compact('imported', 'duplicates', 'errors');
    }

    private function rows(UploadedFile $file): array
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        if (in_array($extension, ['xls', 'xlsx'], true)) {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();

            return $rows;
        }
        $handle = fopen($file->getRealPath(), 'rb');
        throw_if($handle === false, new RuntimeException('No se pudo leer el archivo CSV.'));
        $firstLine = fgets($handle);
        throw_if($firstLine === false, new RuntimeException('El archivo CSV está vacío.'));
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, separator: $delimiter)) !== false) $rows[] = $row;
        fclose($handle);

        return $rows;
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)->replace("\xEF\xBB\xBF", '')->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    private function column(array $headers, array $names): ?int
    {
        foreach ($names as $name) {
            foreach ($headers as $index => $header) {
                if ($header === $name || str_starts_with($header, $name.'_')) return $index;
            }
        }
        return null;
    }

    private function date(mixed $value): string
    {
        if (is_numeric($value)) {
            try { return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString(); } catch (\Throwable) {}
        }
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
