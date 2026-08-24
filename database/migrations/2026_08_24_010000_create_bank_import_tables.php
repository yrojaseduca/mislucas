<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('enable_banking');
            $table->string('provider_name')->nullable();
            $table->string('status')->default('active');
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id');
            $table->string('kind', 20);
            $table->string('display_name');
            $table->char('currency', 3);
            $table->string('provider_id')->nullable();
            $table->timestamps();
            $table->unique(['bank_connection_id', 'external_id']);
        });

        Schema::create('bank_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id');
            $table->string('type', 20);
            $table->bigInteger('amount');
            $table->dateTime('occurred_at');
            $table->string('description');
            $table->string('merchant_name')->nullable();
            $table->json('classification')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['bank_account_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('bank_connections');
    }
};
