<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('paid_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->string('type', 20);
            $table->bigInteger('amount');
            $table->string('description');
            $table->text('notes')->nullable();
            $table->json('splits');
            $table->string('frequency', 20);
            $table->date('next_run_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'next_run_on']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('recurring_transaction_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unique(['recurring_transaction_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recurring_transaction_id');
        });
        Schema::dropIfExists('recurring_transactions');
    }
};
