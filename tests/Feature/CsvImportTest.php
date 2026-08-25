<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_import_csv_rows_into_pending_inbox_without_duplicates(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $workspace->members()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => 'owner']);
        $workspace->accounts()->create(['name' => 'Cuenta principal']);
        $csv = "Fecha;Concepto;Importe;Tipo\n24/08/2026;Supermercado;-45,90;gasto\n2026-08-25;Nómina;1.250,50;ingreso\n";

        $response = $this->actingAs($user)->post('/api/workspaces/'.$workspace->id.'/bank/import-csv', [
            'file' => UploadedFile::fake()->createWithContent('gastos.csv', $csv),
        ])->assertOk()->assertJson(['imported' => 2, 'duplicates' => 0, 'errors' => []]);

        $this->assertDatabaseHas('bank_transactions', ['description' => 'Supermercado', 'amount' => 4590, 'type' => 'expense', 'status' => 'pending']);
        $this->assertDatabaseHas('bank_transactions', ['description' => 'Nómina', 'amount' => 125050, 'type' => 'income', 'status' => 'pending']);

        $this->actingAs($user)->withHeader('Accept', 'application/json')->post('/api/workspaces/'.$workspace->id.'/bank/import-csv', [
            'file' => UploadedFile::fake()->createWithContent('gastos.csv', $csv),
        ])->assertOk()->assertJson(['imported' => 0, 'duplicates' => 2]);
        $this->assertSame(2, BankTransaction::query()->count());
    }

    public function test_csv_requires_expected_columns(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $workspace->members()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => 'owner']);

        $this->actingAs($user)->withHeader('Accept', 'application/json')->post('/api/workspaces/'.$workspace->id.'/bank/import-csv', [
            'file' => UploadedFile::fake()->createWithContent('incorrecto.csv', "Nombre;Valor\nUno;10\n"),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }
}
