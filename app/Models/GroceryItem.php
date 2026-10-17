<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroceryItem extends Model
{
    use BelongsToWorkspace, HasFactory;

    public const CATEGORIES = [
        'groceries' => ['name' => 'بقالة وتموين', 'icon' => '🛒', 'color' => '#10b981'],
        'produce' => ['name' => 'خضار وفواكه', 'icon' => '🥦', 'color' => '#84cc16'],
        'dairy' => ['name' => 'ألبان وأجبان', 'icon' => '🥛', 'color' => '#38bdf8'],
        'cleaning' => ['name' => 'منظفات ومنزل', 'icon' => '🧼', 'color' => '#6366f1'],
        'pharmacy' => ['name' => 'صيدلية وعناية', 'icon' => '💊', 'color' => '#ec4899'],
        'meat' => ['name' => 'لحوم ودواجن', 'icon' => '🥩', 'color' => '#ef4444'],
        'other' => ['name' => 'أخرى', 'icon' => '📦', 'color' => '#94a3b8'],
    ];

    protected $fillable = [
        'workspace_id',
        'user_id',
        'name',
        'category',
        'estimated_price',
        'is_bought',
        'bought_by',
        'bought_at',
        'transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'estimated_price' => 'decimal:2',
            'is_bought' => 'boolean',
            'bought_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bought_by');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function getCategoryMetaAttribute(): array
    {
        return self::CATEGORIES[$this->category] ?? self::CATEGORIES['other'];
    }

    public function scopeActive($query)
    {
        return $query->whereNull('transaction_id');
    }

    public function scopePending($query)
    {
        return $query->whereNull('transaction_id')->where('is_bought', false);
    }

    public function scopeBought($query)
    {
        return $query->whereNull('transaction_id')->where('is_bought', true);
    }
}
