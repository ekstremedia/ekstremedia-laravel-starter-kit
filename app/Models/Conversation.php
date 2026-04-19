<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string|null $title
 * @property bool $is_group
 * @property int $created_by
 * @property Carbon|null $last_message_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $creator
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, Message> $messages
 * @property-read Message|null $latestMessage
 * @property-read Pivot|null $pivot
 */
class Conversation extends Model
{
    public function getConnectionName(): ?string
    {
        return config('chat.connection', 'pgsql');
    }

    protected $fillable = ['title', 'is_group', 'created_by', 'last_message_at'];

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Scope to conversations where the given user is a participant.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('users', fn (Builder $q) => $q->where('user_id', $userId));
    }

    /**
     * Count messages the user hasn't read yet in this conversation.
     */
    public function unreadCountFor(User $user): int
    {
        $pivotRow = DB::connection(config('chat.connection', 'pgsql'))
            ->table('conversation_user')
            ->where('conversation_id', $this->id)
            ->where('user_id', $user->id)
            ->first();
        $lastRead = $pivotRow?->last_read_at;

        $query = $this->messages()->where('user_id', '!=', $user->id);

        if ($lastRead) {
            $query->where('created_at', '>', $lastRead);
        }

        return $query->count();
    }

    /**
     * Check if a user is a participant in this conversation.
     */
    public function isParticipant(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Find an existing 1:1 conversation between two users, if any.
     */
    public static function findDirectBetween(int $userIdA, int $userIdB): ?self
    {
        return static::where('is_group', false)
            ->whereHas('users', fn (Builder $q) => $q->where('user_id', $userIdA))
            ->whereHas('users', fn (Builder $q) => $q->where('user_id', $userIdB))
            ->get()
            ->first(fn (self $c) => $c->users()->count() === 2);
    }
}
