<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $juan = User::factory()->create(['name' => 'Juan', 'email' => 'juan@example.com']);
        $maria = User::factory()->create(['name' => 'María', 'email' => 'maria@example.com']);
        $home = Workspace::query()->create(['name' => 'Casa', 'type' => 'household', 'currency' => 'EUR']);
        $juanMember = $home->members()->create(['user_id' => $juan->id, 'display_name' => 'Juan', 'role' => 'owner']);
        $mariaMember = $home->members()->create(['user_id' => $maria->id, 'display_name' => 'María', 'role' => 'member']);
        $account = Account::query()->create(['workspace_id' => $home->id, 'name' => 'Cuenta conjunta', 'opening_balance' => 250000]);
        $category = Category::query()->create(['workspace_id' => $home->id, 'name' => 'Compra', 'icon' => 'pi-shopping-cart']);
        $movement = Transaction::query()->create(['workspace_id' => $home->id, 'account_id' => $account->id, 'category_id' => $category->id, 'paid_by_member_id' => $mariaMember->id, 'created_by_user_id' => $maria->id, 'type' => 'expense', 'amount' => 8000, 'occurred_at' => now(), 'description' => 'Mercadona']);
        $movement->splits()->createMany([
            ['member_id' => $juanMember->id, 'amount' => 4000, 'percentage' => 50],
            ['member_id' => $mariaMember->id, 'amount' => 4000, 'percentage' => 50],
        ]);
    }
}
