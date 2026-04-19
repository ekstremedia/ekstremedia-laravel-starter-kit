<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['first_name', 'last_name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    public const ROLE_ADMIN = 'Admin';

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Impersonate, InteractsWithMedia, LogsActivity, Notifiable, Searchable, TwoFactorAuthenticatable;

    public function canImpersonate(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->hasRole(self::ROLE_ADMIN);
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function ban(?string $reason = null): void
    {
        $this->forceFill([
            'banned_at' => now(),
            'banned_reason' => $reason,
        ])->save();
    }

    public function unban(): void
    {
        $this->forceFill([
            'banned_at' => null,
            'banned_reason' => null,
        ])->save();
    }

    public function scopeBanned($query)
    {
        return $query->whereNotNull('banned_at');
    }

    public function scopeNotBanned($query)
    {
        return $query->whereNull('banned_at');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 64, 64)
            ->format('webp')
            ->nonQueued()
            ->performOnCollections('avatar');

        $this->addMediaConversion('avatar')
            ->fit(Fit::Crop, 256, 256)
            ->format('webp')
            ->performOnCollections('avatar');
    }

    public function avatarUrl(string $conversion = 'avatar'): ?string
    {
        $media = $this->getFirstMedia('avatar');

        if (! $media) {
            return null;
        }

        return $media->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : $media->getUrl();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'email', 'email_verified_at'])
            ->logOnlyDirty()
            ->useLogName('user');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
        ];
    }

    public function setting(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    /**
     * Conversations this user is a participant in.
     *
     * @return BelongsToMany<Conversation, $this>
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * Count total unread messages across all conversations for this user.
     * Single aggregate query joining the conversation_user pivot.
     */
    public function unreadMessagesCount(): int
    {
        return Message::query()
            ->join('conversation_user', 'conversation_user.conversation_id', '=', 'messages.conversation_id')
            ->where('conversation_user.user_id', $this->id)
            ->where('messages.user_id', '!=', $this->id)
            ->where(function ($q): void {
                $q->whereNull('conversation_user.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'conversation_user.last_read_at');
            })
            ->count();
    }

    /**
     * Customers (a.k.a. tenants in package-speak) this user is a member of.
     * The underlying model is `App\Models\Tenant` because stancl/tenancy's base
     * contract names it that way; at the application layer we call them customers.
     *
     * @return BelongsToMany<Tenant, $this>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withTimestamps();
    }

    public function belongsToCustomer(Tenant $customer): bool
    {
        return $this->customers()->whereKey($customer->getKey())->exists();
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
     * Send the email verification notification using the MJML template.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * Send the password reset notification using the MJML template.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
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
            'banned_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
