<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Services\CurrencyService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalarySource extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'amount',
        'currency',
        'payment_method',
        'day_of_month',
        'expected_date',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'day_of_month' => 'integer',
        'expected_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get amount converted to base currency (Shekel ILS).
     */
    public function getAmountInShekelAttribute(): float
    {
        $curr = strtoupper($this->currency ?? 'ILS');
        if ($curr === 'ILS') {
            return (float) $this->amount;
        }

        return CurrencyService::convert((float) $this->amount, $curr, 'ILS');
    }

    /**
     * Get currency metadata (name, symbol, flag).
     */
    public function getCurrencyMetaAttribute(): array
    {
        $curr = strtoupper($this->currency ?? 'ILS');
        $all = CurrencyService::getCurrencies();

        return $all[$curr] ?? ['name' => $curr, 'symbol' => $curr, 'flag' => '🌐'];
    }

    /**
     * Get human-friendly payment method label.
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'transfer' => '📱 تطبيق إلكتروني / بنكي',
            'cash' => '💵 نقداً (كاش)',
            'card' => '💳 بطاقة بنكية',
            default => '📱 إلكتروني',
        };
    }

    /**
     * Get status and countdown information for this salary stream.
     */
    public function getStatusMetaAttribute(): array
    {
        $today = Carbon::today();
        $targetDay = $this->day_of_month ?? 1;

        if ($today->day == $targetDay) {
            return [
                'status' => 'today',
                'badge' => '🎉 اليوم موعد الاستلام!',
                'color' => '#10b981',
                'days_left' => 0,
            ];
        } elseif ($today->day < $targetDay) {
            $daysLeft = $targetDay - $today->day;

            return [
                'status' => 'upcoming',
                'badge' => "⏳ قادم خلال {$daysLeft} يوم",
                'color' => '#3b82f6',
                'days_left' => $daysLeft,
            ];
        } else {
            return [
                'status' => 'received',
                'badge' => '✅ تم موعد القبض للشهر الحالي',
                'color' => '#9ca3af',
                'days_left' => null,
            ];
        }
    }
}
