<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalHarvestLog extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'goal_id',
        'transaction_id',
        'date',
        'day_allowance',
        'day_spent',
        'surplus_amount',
        'harvested_amount',
        'harvested_in_goal_currency',
        'currency',
        'exchange_rate',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'day_allowance' => 'decimal:2',
        'day_spent' => 'decimal:2',
        'surplus_amount' => 'decimal:2',
        'harvested_amount' => 'decimal:2',
        'harvested_in_goal_currency' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    public function goal(): BelongsTo
    {
        return $this->belongsTo(SavingGoal::class, 'goal_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
