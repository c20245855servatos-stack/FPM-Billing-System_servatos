<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        foreach ([
            ['company_name' => 'Northstar Studio', 'email' => 'billing@northstar.test'],
            ['company_name' => 'Summit Creative', 'email' => 'billing@summit.test'],
            ['company_name' => 'Brightline Agency', 'email' => 'billing@brightline.test'],
        ] as $client) {
            Client::updateOrCreate(['email' => $client['email']], $client);
        }

        $sandy = Client::updateOrCreate(
            ['email' => 'sandy@gmail.com'],
            ['company_name' => 'Sandy Corp'],
        );
        $acme = Client::updateOrCreate(
            ['email' => 'acme@gmail.com'],
            ['company_name' => 'Acme Corp'],
        );
        $beta = Client::updateOrCreate(
            ['email' => 'beta@gmail.com'],
            ['company_name' => 'Beta LLC'],
        );

        $sandyWebsite = Project::updateOrCreate(
            ['client_id' => $sandy->id, 'name' => 'Website Redesign'],
        );
        $acmeWebsite = Project::updateOrCreate(
            ['client_id' => $acme->id, 'name' => 'Acme Website Redesign'],
        );
        $betaMobile = Project::updateOrCreate(
            ['client_id' => $beta->id, 'name' => 'Mobile App'],
        );
        $betaWebsite = Project::updateOrCreate(
            ['client_id' => $beta->id, 'name' => 'Website'],
        );

        $milestones = [
            [$sandyWebsite, 'Phase 1: Mockups', 10000, '2026-01-11', 'paid'],
            [$acmeWebsite, 'Phase 2: Coding', 25000, '2026-12-01', 'invoiced'],
            [$betaMobile, 'UI Design', 1500, '2026-11-15', 'paid'],
            [$betaWebsite, 'UI Design', 1000, '2026-11-15', 'paid'],
        ];

        $createdMilestones = [];
        foreach ($milestones as [$project, $title, $amount, $dueDate, $status]) {
            $createdMilestones[] = ProjectMilestone::updateOrCreate(
                [
                    'project_id' => $project->id,
                    'title' => $title,
                ],
                [
                    'amount' => $amount,
                    'due_date' => $dueDate,
                    'status' => $status,
                ],
            );
        }

        $this->seedInvoice($createdMilestones[0], 0.10, 'paid', [
            ['amount_paid' => 5500, 'paid_at' => '2026-10-25'],
            ['amount_paid' => 5500, 'paid_at' => '2026-10-26'],
        ]);
        $this->seedInvoice($createdMilestones[1], 0.10, 'unpaid');
        $this->seedInvoice($createdMilestones[2], 0.05, 'paid', [
            ['amount_paid' => 1575, 'paid_at' => '2026-10-26'],
        ]);
        $this->seedInvoice($createdMilestones[3], 0.10, 'paid', [
            ['amount_paid' => 1100, 'paid_at' => '2026-10-26'],
        ]);
    }

    private function seedInvoice(
        ProjectMilestone $milestone,
        float $taxRate,
        string $status,
        array $payments = [],
    ): void {
        $amount = (float) $milestone->amount;
        $taxAmount = round($amount * $taxRate, 2);

        $invoice = Invoice::updateOrCreate(
            ['milestone_id' => $milestone->id],
            [
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total_amount_due' => round($amount + $taxAmount, 2),
                'status' => $status,
            ],
        );

        foreach ($payments as $payment) {
            InvoicePayment::firstOrCreate(
                [
                    'invoice_id' => $invoice->id,
                    'amount_paid' => $payment['amount_paid'],
                    'paid_at' => $payment['paid_at'].' 00:00:00',
                ],
                [
                    'method' => 'bank_transfer',
                ],
            );
        }
    }
}
