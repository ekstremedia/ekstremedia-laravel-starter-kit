<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'site_up' => 'boolean',
        'registration_open' => 'boolean',
        'login_enabled' => 'boolean',
        'require_email_verification' => 'boolean',
        'require_2fa_for_admins' => 'boolean',
        'send_welcome_notification' => 'boolean',
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
        ]);
    }
}
