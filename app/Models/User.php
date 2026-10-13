<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'pin_code',
        'pin_enabled',
        'role',
        'workspace_id',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'pin_code',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin_enabled' => 'boolean',
        ];
    }

    /**
     * Check if user is system administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Workspace this user belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Check if user has a set PIN.
     */
    public function hasPin(): bool
    {
        return ! empty($this->pin_code) && $this->pin_enabled;
    }

    /**
     * Verify a 4-digit PIN against the user's stored PIN.
     */
    public function verifyPin(string $pin): bool
    {
        if (empty($this->pin_code)) {
            return false;
        }

        return Hash::check($pin, $this->pin_code);
    }

    /**
     * Transactions created by this user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Debts recorded by this user.
     */
    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }
}
