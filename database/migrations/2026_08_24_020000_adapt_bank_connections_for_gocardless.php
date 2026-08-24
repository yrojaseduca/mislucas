<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_connections', function (Blueprint $table): void {
            $table->string('external_id')->nullable()->after('provider');
            $table->string('institution_id')->nullable()->after('external_id');
            $table->text('access_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bank_connections', function (Blueprint $table): void {
            $table->dropColumn(['external_id', 'institution_id']);
        });
    }
};
