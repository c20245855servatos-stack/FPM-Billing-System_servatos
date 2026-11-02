<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectMilestone extends Model
{
    protected $fillable = ['project_id', 'title', 'amount', 'due_date', 'status'];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'milestone_id');
    }
}
