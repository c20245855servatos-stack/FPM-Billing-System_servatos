<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Default tax rate for all milestones in this project (e.g. 0.12 = 12%)
            // Used to estimate tax before invoices are created
            $table->decimal('default_tax_rate', 5, 4)->default(0.1200)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('default_tax_rate');
        });
    }
};
