<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_active')->default(true)->after('is_superadmin'));
        Schema::table('workspaces', fn (Blueprint $table) => $table->timestamp('archived_at')->nullable()->after('currency'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_active'));
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn('archived_at'));
    }
};
