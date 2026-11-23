<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $invoices = DB::table('invoices')
            ->select('id', 'total_amount_due', 'status')
            ->get();

        foreach ($invoices as $invoice) {
            $amountPaid = (float) DB::table('invoice_payments')
                ->where('invoice_id', $invoice->id)
                ->sum('amount_paid');
            $totalDue = (float) $invoice->total_amount_due;

            $status = $amountPaid >= $totalDue
                ? 'paid'
                : ($amountPaid > 0 ? 'partial' : 'unpaid');

            DB::table('invoices')->where('id', $invoice->id)->update([
                'status' => $status,
                'updated_at' => now(),
            ]);

            $milestoneStatus = $status === 'paid' ? 'paid' : 'invoiced';
            DB::table('project_milestones')
                ->whereIn(
                    'id',
                    DB::table('invoices')->where('id', $invoice->id)->pluck('milestone_id'),
                )
                ->update(['status' => $milestoneStatus]);
        }
    }

    public function down(): void
    {
    }
};
