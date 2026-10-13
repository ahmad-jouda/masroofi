<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'currency',
        'created_by',
    ];

    /**
     * Users belonging to this workspace.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Financial settings for this workspace.
     */
    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    /**
     * Envelopes in this workspace.
     */
    public function envelopes(): HasMany
    {
        return $this->hasMany(Envelope::class);
    }

    /**
     * Saving goals in this workspace.
     */
    public function savingGoals(): HasMany
    {
        return $this->hasMany(SavingGoal::class);
    }

    /**
     * Transactions in this workspace.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Debts in this workspace.
     */
    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    /**
     * Salary sources in this workspace.
     */
    public function salarySources(): HasMany
    {
        return $this->hasMany(SalarySource::class);
    }

    /**
     * Subscriptions in this workspace.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Wishlist in this workspace.
     */
    public function wishlist(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }
}
