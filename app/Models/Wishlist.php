<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $table = 'wishlist';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'title',
        'price',
        'duration_hours',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_hours' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getHoursRemainingAttribute(): int
    {
        $elapsed = $this->created_at->diffInHours(now());

        return max(0, $this->duration_hours - $elapsed);
    }
}
