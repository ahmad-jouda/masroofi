<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Envelope extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'icon',
        'budget',
        'pillar',
        'color',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getSpentAttribute(): float
    {
        if (array_key_exists('cycle_spent', $this->attributes)) {
            return (float) $this->attributes['cycle_spent'];
        }

        return (float) $this->transactions()
            ->where('type', 'expense')
            ->sum('amount');
    }

    public function getRemainingAttribute(): float
    {
        if ($this->budget <= 0) {
            return 0;
        }

        return max(0, (float) $this->budget - $this->spent);
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->budget <= 0) {
            return 0;
        }

        return min(100, round(($this->spent / $this->budget) * 100, 1));
    }
}
