<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @phpstan-type UserSettingsShape array{
 *     locale: string,
 *     dark_mode: bool,
 *     notification_email_immediate: bool,
 *     notification_digest: 'none'|'daily'|'weekly',
 *     notification_chat_messages: bool,
 *     notification_account_updates: bool,
 *     notification_system_alerts: bool,
 *     notification_storage_alerts: bool,
 *     files_enabled: bool,
 *     storage_quota_bytes: int|null,
 *     storage_last_alerted_threshold: array<string, int>|null,
 *     last_customer_slug: string|null,
 * }
 */
class UserSetting extends Model
{
    protected $fillable = ['user_id', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Default values for all settings.
     * Add new settings here — they are automatically merged when reading.
     *
     * @var UserSettingsShape
     */
    public static array $defaults = [
        'locale' => 'en',
        'dark_mode' => true,
        'notification_email_immediate' => false,
        'notification_digest' => 'none', // 'none', 'daily', 'weekly'
        'notification_chat_messages' => true,
        'notification_account_updates' => true,
        'notification_system_alerts' => true,
        'notification_storage_alerts' => true,
        // Personal file-system opt-in. Even if the tenant feature flag is on,
        // a user only sees /files when this is true. Admin-controlled.
        'files_enabled' => false,
        // null = unlimited, 0 = hard-disabled, otherwise byte cap.
        'storage_quota_bytes' => null,
        // Highest threshold (80/95/100) we've already notified about, so we
        // don't spam the same warning every upload. Reset to null on delete.
        'storage_last_alerted_threshold' => null,
        // Most recently visited customer slug — used by CustomerLandingController
        // to auto-redirect returning users instead of forcing them through the picker.
        'last_customer_slug' => null,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Return settings merged with defaults, so missing keys always have a value.
     */
    public function resolved(): array
    {
        return array_merge(static::$defaults, $this->settings ?? []);
    }

    /**
     * Merge a partial array of settings into the existing ones.
     */
    public function merge(array $partial): void
    {
        $this->settings = array_merge($this->resolved(), $partial);
        $this->save();
    }
}
