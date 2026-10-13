<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtHarvestLog extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'debt_id',
        'date',
        'day_allowance',
        'day_spent',
        'surplus_amount',
        'harvested_amount',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'day_allowance' => 'decimal:2',
        'day_spent' => 'decimal:2',
        'surplus_amount' => 'decimal:2',
        'harvested_amount' => 'decimal:2',
    ];

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }
}
