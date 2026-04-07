<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    protected $fillable = ['user_id', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Default values for all settings.
     * Add new settings here — they are automatically merged when reading.
     */
    public static array $defaults = [
        'locale'    => 'en',
        'dark_mode' => false,
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
