<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Invoice;
use App\Models\InvoicePayment;

class DomainService
{
    public function __construct(
        private ClientService $clients,
        private ProjectService $projects,
        private ProjectMilestoneService $milestones,
        private InvoiceService $invoices,
    ) {}

    public function createClient(array $data): Client
    {
        return $this->clients->createClient($data);
    }

    public function updateClient(Client $client, array $data): Client
    {
        return $this->clients->updateClient($client, $data);
    }

    /* ============================================================
     *  PROJECT OPERATIONS
     * ============================================================ */

    public function createProject(array $data): Project
    {
        return $this->projects->createProject($data);
    }

    public function updateProject(Project $project, array $data): Project
    {
        return $this->projects->updateProject($project, $data);
    }

    /* ============================================================
     *  MILESTONE OPERATIONS
     * ============================================================ */

    public function createMilestone(array $data): ProjectMilestone
    {
        return $this->milestones->createMilestone($data);
    }

    public function updateMilestone(ProjectMilestone $milestone, array $data): ProjectMilestone
    {
        return $this->milestones->updateMilestone($milestone, $data);
    }

    /* ============================================================
     *  INVOICE OPERATIONS — BUSINESS RULES
     * ============================================================ */

    public function createInvoice(array $data): Invoice
    {
        return $this->invoices->createInvoice($data);
    }

    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        return $this->invoices->updateInvoice($invoice, $data);
    }

    public function recordPayment(Invoice $invoice, array $data): InvoicePayment
    {
        return $this->invoices->recordPayment($invoice, $data);
    }

    /* ============================================================
     *  SANITIZATION HELPERS
     * ============================================================ */

    public function sanitizeEmail(string $email): string
    {
        return $this->clients->sanitizeEmail($email);
    }

    public function formatCurrency(float $value): float
    {
        return round($value, 2);
    }
}
