<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_budget_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('default_amount');
            $table->string('rollover_policy', 20)->default('expires');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['workspace_id', 'category_id']);
        });

        Schema::table('budgets', function (Blueprint $table): void {
            $table->foreignId('monthly_budget_rule_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->boolean('is_override')->default(true)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('monthly_budget_rule_id');
            $table->dropColumn('is_override');
        });
        Schema::dropIfExists('monthly_budget_rules');
    }
};
