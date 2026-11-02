<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->unique()->constrained('project_milestones')->onDelete('cascade');
            $table->decimal('tax_rate', 5, 4); // e.g., 0.0750 for 7.5%
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('total_amount_due', 12, 2);
            $table->enum('status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
