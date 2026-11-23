<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->decimal('admin_counter_price', 12, 2)->nullable()->after('price');
            $table->timestamp('client_agreed_at')->nullable()->after('admin_counter_price');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['description', 'admin_counter_price', 'client_agreed_at']);
        });
    }
};
