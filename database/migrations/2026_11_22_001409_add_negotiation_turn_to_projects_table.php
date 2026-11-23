<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Tracks whose counter is currently on the table: 'admin' or 'client'
            $table->string('last_counter_by')->nullable()->after('admin_counter_price');
            // The price the client counter-proposed back to the admin
            $table->decimal('client_counter_price', 12, 2)->nullable()->after('last_counter_by');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['last_counter_by', 'client_counter_price']);
        });
    }
};
