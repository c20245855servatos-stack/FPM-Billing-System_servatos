<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    protected $fillable = ['invoice_id', 'amount_paid', 'paid_at', 'method'];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount_paid' => 'decimal:2',
    ];

    protected function formattedAmountPaid(): Attribute
    {
        return Attribute::get(fn (): string => number_format((float) $this->amount_paid, 2, '.', ','));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

