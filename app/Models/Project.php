<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['client_id', 'name', 'description', 'price', 'default_tax_rate', 'admin_counter_price', 'client_counter_price', 'last_counter_by', 'client_agreed_at', 'approval_status', 'archived_at'];

    protected $casts = [
        'price'               => 'decimal:2',
        'default_tax_rate'    => 'decimal:4',
        'admin_counter_price' => 'decimal:2',
        'client_counter_price'=> 'decimal:2',
        'archived_at'         => 'datetime',
        'client_agreed_at'    => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** Scope: only active (non-archived) projects. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /** Scope: only archived projects. */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }
}
