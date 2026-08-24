<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name');
            $table->date('month');
            $table->bigInteger('amount');
            $table->string('rollover_policy', 20)->default('expires');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'month', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
