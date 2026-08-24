<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bank_connections')->where('provider', 'gocardless')->update(['provider' => 'enable_banking', 'status' => 'expired']);
    }

    public function down(): void
    {
        DB::table('bank_connections')->where('provider', 'enable_banking')->update(['provider' => 'gocardless']);
    }
};
