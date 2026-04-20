<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $guarded = ['id'];

    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }

    protected $casts = [
        'site_up' => 'boolean',
        'registration_open' => 'boolean',
        'login_enabled' => 'boolean',
        'require_email_verification' => 'boolean',
        'require_2fa_for_admins' => 'boolean',
        'send_welcome_notification' => 'boolean',
        'files_feature_enabled' => 'boolean',
        'max_share_days' => 'integer',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'site_up' => true,
            'registration_open' => true,
            'login_enabled' => true,
            'require_email_verification' => true,
            'default_role' => 'User',
            'require_2fa_for_admins' => false,
            'send_welcome_notification' => true,
            'announcement_severity' => 'info',
            'files_feature_enabled' => false,
            'max_share_days' => 7,
        ]);
    }
}
