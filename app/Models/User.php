<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['first_name', 'last_name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function setting(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    /**
     * Get the user's settings, creating defaults if none exist yet.
     */
    public function settings(): UserSetting
    {
        // firstOrCreate is idempotent — safe to call multiple times per request
        $record = $this->setting()->firstOrCreate([], ['settings' => []]);

        // Keep the relationship cache in sync so subsequent ->setting accesses don't re-query
        $this->setRelation('setting', $record);

        return $record;
    }

    /**
     * Get the user's full name.
     */
    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

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
        ];
    }
}
