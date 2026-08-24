<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type', 20);
            $table->char('currency', 3)->default('EUR');
            $table->timestamps();
        });

        Schema::create('workspace_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('role', 20)->default('member');
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('bank');
            $table->bigInteger('opening_balance')->default(0);
            $table->boolean('is_shared')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('color', 20)->nullable();
            $table->string('kind', 20)->default('expense');
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('paid_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->string('type', 20);
            $table->bigInteger('amount');
            $table->date('occurred_at');
            $table->string('description');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'occurred_at']);
        });

        Schema::create('transaction_splits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->decimal('percentage', 7, 4)->nullable();
            $table->timestamps();
            $table->unique(['transaction_id', 'member_id']);
        });

        Schema::create('settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_member_id')->constrained('workspace_members');
            $table->foreignId('to_member_id')->constrained('workspace_members');
            $table->bigInteger('amount');
            $table->date('settled_at');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('transaction_splits');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
