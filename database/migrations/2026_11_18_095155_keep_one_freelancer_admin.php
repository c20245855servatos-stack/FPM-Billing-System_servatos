<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'admin')
            ->where('email', '!=', 'admin@example.com')
            ->update(['role' => 'user']);

        DB::table('users')
            ->where('email', 'admin@example.com')
            ->update(['role' => 'admin']);
    }

    public function down(): void
    {
    }
};
