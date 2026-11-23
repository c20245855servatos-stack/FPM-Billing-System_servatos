<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $invoices = DB::table('invoices')
            ->join('project_milestones', 'project_milestones.id', '=', 'invoices.milestone_id')
            ->where('invoices.tax_rate', 0.10)
            ->select(
                'invoices.id',
                'invoices.status',
                'project_milestones.amount',
            )
            ->get();

        foreach ($invoices as $invoice) {
            $subtotal = (float) $invoice->amount;
            $taxAmount = round($subtotal * 0.12, 2);
            $totalDue = round($subtotal + $taxAmount, 2);
            $amountPaid = (float) DB::table('invoice_payments')
                ->where('invoice_id', $invoice->id)
                ->sum('amount_paid');

            $status = $amountPaid >= $totalDue
                ? 'paid'
                : ($amountPaid > 0 ? 'partial' : 'unpaid');

            DB::table('invoices')->where('id', $invoice->id)->update([
                'tax_rate' => 0.12,
                'tax_amount' => $taxAmount,
                'total_amount_due' => $totalDue,
                'status' => $status,
                'updated_at' => now(),
            ]);
        }

        DB::table('project_milestones')
            ->join('invoices', 'invoices.milestone_id', '=', 'project_milestones.id')
            ->where('invoices.status', 'paid')
            ->update(['project_milestones.status' => 'paid']);

        DB::table('project_milestones')
            ->join('invoices', 'invoices.milestone_id', '=', 'project_milestones.id')
            ->whereIn('invoices.status', ['unpaid', 'partial'])
            ->update(['project_milestones.status' => 'invoiced']);
    }

    public function down(): void
    {
        // This migration corrects legacy tax data; no automatic reverse conversion.
    }
};
