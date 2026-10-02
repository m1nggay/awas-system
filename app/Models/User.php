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
