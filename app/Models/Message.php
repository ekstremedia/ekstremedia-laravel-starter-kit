<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property string $body
 * @property bool $is_encrypted
 * @property string $type
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Conversation $conversation
 * @property-read User $user
 */
class Message extends Model
{
    protected $connection = 'pgsql';

    protected $fillable = ['conversation_id', 'user_id', 'body', 'is_encrypted', 'type'];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Transparently encrypt/decrypt the message body based on config and stored flag.
     */
    protected function body(): Attribute
    {
        return Attribute::make(
            get: function (string $value): string {
                if ($this->is_encrypted) {
                    try {
                        return Crypt::decryptString($value);
                    } catch (\Throwable) {
                        return '[encrypted message — unable to decrypt]';
                    }
                }

                return $value;
            },
            set: function (string $value): array {
                if (config('chat.encryption_enabled')) {
                    return [
                        'body' => Crypt::encryptString($value),
                        'is_encrypted' => true,
                    ];
                }

                return [
                    'body' => $value,
                    'is_encrypted' => false,
                ];
            },
        );
    }
}
