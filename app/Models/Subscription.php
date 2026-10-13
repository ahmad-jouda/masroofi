<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'cost',
        'period',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
    ];

    public function getYearlyCostAttribute(): float
    {
        return $this->period === 'monthly' ? ($this->cost * 12) : $this->cost;
    }

    public function getMonthlyCostAttribute(): float
    {
        return $this->period === 'monthly' ? $this->cost : ($this->cost / 12);
    }
}
