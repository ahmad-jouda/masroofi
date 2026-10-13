<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'salary',
        'salary_day',
        'cycle_mode',
        'work_hours_per_week',
        'savings_target_percent',
        'currency',
        'theme',
        'sound_enabled',
        'saved_from_impulse',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'saved_from_impulse' => 'decimal:2',
        'sound_enabled' => 'boolean',
        'salary_day' => 'integer',
        'work_hours_per_week' => 'integer',
        'savings_target_percent' => 'integer',
    ];
}
