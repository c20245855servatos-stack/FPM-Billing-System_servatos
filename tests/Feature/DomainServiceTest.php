<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Services\DomainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DomainServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_email_is_lowercased(): void
    {
        $client = app(DomainService::class)->createClient([
            'company_name' => 'Example Corp',
            'email' => '  Example@GMAIL.com ',
        ]);

        $this->assertSame('example@gmail.com', $client->email);
    }

    public function test_invoice_tax_and_total_are_calculated_in_the_service(): void
    {
        $milestone = $this->createMilestone(1500);

        $invoice = app(DomainService::class)->createInvoice([
            'milestone_id' => $milestone->id,
            'tax_rate' => 0.05,
        ]);

        $this->assertEquals('75.00', $invoice->tax_amount);
        $this->assertEquals('1575.00', $invoice->total_amount_due);
        $this->assertSame('invoiced', $milestone->fresh()->status);
    }

    public function test_payment_marks_invoice_and_milestone_paid(): void
    {
        $milestone = $this->createMilestone(1000);
        $service = app(DomainService::class);
        $invoice = $service->createInvoice([
            'milestone_id' => $milestone->id,
            'tax_rate' => 0.10,
        ]);

        $service->recordPayment($invoice, [
            'amount_paid' => 1100,
            'method' => 'bank_transfer',
        ]);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('paid', $milestone->fresh()->status);
    }

    public function test_payment_cannot_exceed_invoice_balance(): void
    {
        $service = app(DomainService::class);
        $invoice = $service->createInvoice([
            'milestone_id' => $this->createMilestone(100)->id,
            'tax_rate' => 0,
        ]);

        $this->expectException(ValidationException::class);

        $service->recordPayment($invoice, ['amount_paid' => 101]);
    }

    private function createMilestone(float $amount): ProjectMilestone
    {
        $client = Client::create([
            'company_name' => 'Test Client',
            'email' => 'test@example.com',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Test Project '.uniqid(),
        ]);

        return ProjectMilestone::create([
            'project_id' => $project->id,
            'title' => 'Test Milestone',
            'amount' => $amount,
            'due_date' => '2026-11-30',
        ]);
    }
}
