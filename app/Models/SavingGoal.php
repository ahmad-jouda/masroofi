<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Services\CurrencyService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingGoal extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'icon',
        'target_amount',
        'current_amount',
        'currency',
        'deadline',
        'is_harvest_active',
        'harvest_ratio',
        'harvest_max_daily',
        'harvest_started_at',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'deadline' => 'date',
        'is_harvest_active' => 'boolean',
        'harvest_ratio' => 'decimal:2',
        'harvest_max_daily' => 'decimal:2',
        'harvest_started_at' => 'date',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'goal_id');
    }

    public function harvestLogs(): HasMany
    {
        return $this->hasMany(GoalHarvestLog::class, 'goal_id')->orderBy('date', 'desc');
    }

    public function getHarvestRatioPercentAttribute(): int
    {
        return (int) round(($this->harvest_ratio ?: 0.50) * 100);
    }

    public function getProgressPercentAttribute(): int
    {
        if ($this->target_amount <= 0) {
            return 0;
        }

        return min(100, (int) round(($this->current_amount / $this->target_amount) * 100));
    }

    public function getRemainingNeededAttribute(): float
    {
        return max(0, (float) $this->target_amount - (float) $this->current_amount);
    }

    /**
     * Get the current amount converted to Shekels (ILS).
     */
    public function getEquivalentInShekelAttribute(): float
    {
        $currency = strtoupper($this->currency ?? 'ILS');
        if ($currency === 'ILS') {
            return (float) $this->current_amount;
        }

        return CurrencyService::convert((float) $this->current_amount, $currency, 'ILS');
    }

    /**
     * Get the target amount converted to Shekels (ILS).
     */
    public function getTargetEquivalentInShekelAttribute(): float
    {
        $currency = strtoupper($this->currency ?? 'ILS');
        if ($currency === 'ILS') {
            return (float) $this->target_amount;
        }

        return CurrencyService::convert((float) $this->target_amount, $currency, 'ILS');
    }

    /**
     * Get currency metadata (name, symbol, flag).
     */
    public function getCurrencyMetaAttribute(): array
    {
        $currency = strtoupper($this->currency ?? 'ILS');
        $all = CurrencyService::getCurrencies();

        return $all[$currency] ?? ['name' => $currency, 'symbol' => $currency, 'flag' => '🌐'];
    }
}
