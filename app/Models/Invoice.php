<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = ['milestone_id', 'tax_rate', 'tax_amount', 'total_amount_due', 'status'];

    protected $casts = [
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'total_amount_due' => 'decimal:2',
    ];

    protected function formattedTaxAmount(): Attribute
    {
        return Attribute::get(fn (): string => number_format((float) $this->tax_amount, 2, '.', ','));
    }

    protected function formattedTotalAmountDue(): Attribute
    {
        return Attribute::get(fn (): string => number_format((float) $this->total_amount_due, 2, '.', ','));
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function amountPaid(): float
    {
        return (float) $this->payments()->sum('amount_paid');
    }

    public function balanceDue(): float
    {
        return round((float) $this->total_amount_due - $this->amountPaid(), 2);
    }
}
