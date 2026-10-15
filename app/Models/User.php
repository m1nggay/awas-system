<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends BaseModel implements AuthenticatableContract
{
    use Authenticatable;

    protected $primaryKey = 'user_id';

    protected $hidden = ['password_hash'];

    protected $casts = ['locked_at' => 'datetime'];

    /** Wrong passwords in a row before the account is locked. */
    public const MAX_LOGIN_ATTEMPTS = 5;

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /** Clears a lockout (successful login, password reset, or an administrator's Unlock). */
    public function clearLockout(): void
    {
        if ($this->failed_login_attempts || $this->locked_at) {
            $this->forceFill(['failed_login_attempts' => 0, 'locked_at' => null])->save();
        }
    }

    /** The legacy column name; Laravel's auth reads the hash through this. */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /** No "remember me" column in this schema. */
    public function getRememberTokenName()
    {
        return null;
    }

    public function consumer(): HasOne
    {
        return $this->hasOne(Consumer::class, 'user_id', 'user_id');
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'staff'], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isMeterReader(): bool
    {
        return $this->role === 'staff';
    }

    private ?array $assignedPuroks = null;

    /**
     * Puroks this Meter Reader may read (set in Manage Users). Null means no
     * restriction (administrators); an empty array means none assigned yet.
     */
    public function assignedPurokIds(): ?array
    {
        if (!$this->isMeterReader()) {
            return null;
        }
        return $this->assignedPuroks ??= \Illuminate\Support\Facades\DB::table('meter_reader_puroks')
            ->where('user_id', $this->user_id)->pluck('purok_id')->map(fn ($id) => (int)$id)->all();
    }

    /** Whether this user may read meters / see consumers of the given purok. */
    public function canAccessPurok(int $purokId): bool
    {
        $ids = $this->assignedPurokIds();
        return $ids === null || in_array($purokId, $ids, true);
    }

    /** Name used in "Welcome, …": the username, or the first name for auto-generated applicant usernames. */
    public function welcomeName(): string
    {
        if (str_starts_with((string)$this->username, 'applicant_')) {
            return strtok(trim((string)$this->full_name), ' ') ?: $this->username;
        }
        return $this->username;
    }

    public function dashboardRoute(): string
    {
        return match ($this->role) {
            'admin', 'staff' => 'admin.dashboard',
            'resident'       => 'resident.dashboard',
            'applicant'      => 'applicant.status',
            default          => 'login',
        };
    }
}
