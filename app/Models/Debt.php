<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'name',
        'type',
        'icon',
        'total_debt',
        'notes',
        'status',
        'is_harvest_active',
        'harvest_ratio',
        'harvest_max_daily',
        'surplus_harvested',
        'harvest_started_at',
    ];

    protected $casts = [
        'total_debt' => 'decimal:2',
        'is_harvest_active' => 'boolean',
        'harvest_ratio' => 'decimal:2',
        'harvest_max_daily' => 'decimal:2',
        'surplus_harvested' => 'decimal:2',
        'harvest_started_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function harvestLogs(): HasMany
    {
        return $this->hasMany(DebtHarvestLog::class)->orderBy('date', 'desc');
    }

    public function getHarvestProgressPercentAttribute(): int
    {
        if ($this->total_debt <= 0) {
            return 100;
        }

        return min(100, (int) round(($this->surplus_harvested / $this->total_debt) * 100));
    }

    public function getIsHarvestCompleteAttribute(): bool
    {
        return $this->total_debt > 0 && $this->surplus_harvested >= $this->total_debt;
    }

    public function records(): HasMany
    {
        return $this->hasMany(DebtRecord::class)->orderBy('date', 'desc')->orderBy('id', 'desc');
    }

    public function recalculateBalance(): void
    {
        // Auto-heal: delete orphan debt records whose linked transaction was deleted
        $orphanRecords = $this->records()
            ->whereNotNull('transaction_id')
            ->get()
            ->filter(fn ($r) => ! Transaction::withoutGlobalScopes()->where('id', $r->transaction_id)->exists());

        foreach ($orphanRecords as $orphan) {
            $orphan->delete();
        }

        $added = (float) $this->records()->where('type', 'add_debt')->sum('amount');
        $repaid = (float) $this->records()->where('type', 'repayment')->sum('amount');
        $balance = max(0, $added - $repaid);

        $this->update([
            'total_debt' => $balance,
            'status' => $balance <= 0 ? 'settled' : 'active',
        ]);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'supermarket' => '🛒 سوبرماركت / بقالة (شكك)',
            'person' => '👤 سلفة من شخص',
            'store' => '🏪 متجر / محل تجاري',
            default => '📑 ذمة مالية أخرى',
        };
    }
}
