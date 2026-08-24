<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('symbol')->nullable();
            $table->string('type', 30);
            $table->decimal('quantity', 20, 8)->default(1);
            $table->bigInteger('average_cost')->default(0);
            $table->bigInteger('current_price')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->timestamps();
        });
        Schema::create('debts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('creditor')->nullable();
            $table->bigInteger('original_amount');
            $table->bigInteger('outstanding_balance');
            $table->decimal('annual_interest_rate', 7, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('debt_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('debt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('total_amount');
            $table->bigInteger('principal_amount');
            $table->bigInteger('interest_amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_payments');
        Schema::dropIfExists('debts');
        Schema::dropIfExists('investment_positions');
    }
};
