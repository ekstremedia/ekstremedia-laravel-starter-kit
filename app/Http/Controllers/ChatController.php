<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewChatMessageNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $models = Conversation::forUser($user->id)
            ->with(['users:id,first_name,last_name', 'latestMessage.user:id,first_name,last_name'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();

        $unreadMap = $this->unreadCountsFor($user, $models);

        $conversations = [];

        foreach ($models as $c) {
            $conversations[] = $this->formatConversation($c, $user, $unreadMap[$c->id] ?? 0);
        }

        return Inertia::render('Chat', [
            'conversations' => $conversations,
        ]);
    }

    /**
     * Lightweight JSON endpoint for the chat dropdown.
     */
    public function conversationsJson(Request $request): JsonResponse
    {
        $request->validate([
            'filter' => 'sometimes|in:all,unread,groups',
            'q' => 'sometimes|string|max:100',
        ]);

        $user = $request->user();
        $filter = $request->input('filter', 'all');

        $query = Conversation::forUser($user->id)
            ->with(['users:id,first_name,last_name', 'latestMessage.user:id,first_name,last_name'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at');

        if ($filter === 'groups') {
            $query->where('is_group', true);
        }

        if ($q = $request->input('q')) {
            $matchingUserIds = User::search($q)->keys();
            $query->whereHas('users', fn ($sub) => $sub->whereIn('user_id', $matchingUserIds));
        }

        $models = $query->limit(15)->get();

        $unreadMap = $this->unreadCountsFor($user, $models);

        $conversations = [];

        foreach ($models as $c) {
            $unreadCount = $unreadMap[$c->id] ?? 0;

            // Skip non-unread when filtering
            if ($filter === 'unread' && $unreadCount === 0) {
                continue;
            }

            $conversations[] = $this->formatConversation($c, $user, $unreadCount);
        }

        return response()->json(['conversations' => $conversations]);
    }

    /**
     * Build a conversation_id => unread_count map in a single query.
     *
     * @param  Collection<int, Conversation>  $conversations
     * @return array<int, int>
     */
    private function unreadCountsFor(User $user, Collection $conversations): array
    {
        if ($conversations->isEmpty()) {
            return [];
        }

        $ids = $conversations->pluck('id')->all();

        $rows = Message::query()
            ->selectRaw('messages.conversation_id, COUNT(*) AS unread')
            ->join('conversation_user', 'conversation_user.conversation_id', '=', 'messages.conversation_id')
            ->where('conversation_user.user_id', $user->id)
            ->where('messages.user_id', '!=', $user->id)
            ->whereIn('messages.conversation_id', $ids)
            ->where(function ($q): void {
                $q->whereNull('conversation_user.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'conversation_user.last_read_at');
            })
            ->groupBy('messages.conversation_id')
            ->pluck('unread', 'messages.conversation_id');

        return $rows->map(fn ($v) => (int) $v)->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatConversation(Conversation $c, User $user, int $unreadCount): array
    {
        $latest = $c->latestMessage;

        return [
            'id' => $c->id,
            'title' => $c->title,
            'is_group' => $c->is_group,
            'participants' => $c->users->map(fn (User $u) => [
                'id' => $u->id,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
                'avatar_thumb_url' => $u->avatarUrl('thumb'),
            ])->values()->all(),
            'latest_message' => $latest ? [
                'id' => $latest->id,
                'body' => $latest->body,
                'user_id' => $latest->user_id,
                'user' => [
                    'id' => $latest->user->id,
                    'first_name' => $latest->user->first_name,
                ],
                'created_at' => $latest->created_at->toISOString(),
            ] : null,
            'unread_count' => $unreadCount,
            'last_message_at' => $c->last_message_at?->toISOString(),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|integer|exists:'.config('chat.connection', 'pgsql').'.users,id',
            'title' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $connection = config('chat.connection', 'pgsql');

        $participantIds = collect($validated['user_ids'])
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $user->id)
            ->unique()
            ->values();

        if ($participantIds->isEmpty()) {
            return response()->json(['message' => 'At least one other participant is required.'], 422);
        }

        // Drop banned/invalid users so we match the filter applied in searchUsers.
        $allowedIds = User::on($connection)
            ->notBanned()
            ->whereIn('id', $participantIds)
            ->pluck('id');

        $participantIds = $participantIds->intersect($allowedIds)->values();

        if ($participantIds->isEmpty()) {
            return response()->json(['message' => 'At least one other participant is required.'], 422);
        }

        // For any two-party chat, always dedupe against an existing direct conversation.
        if ($participantIds->count() === 1) {
            $existing = Conversation::findDirectBetween($user->id, $participantIds->first());
            if ($existing) {
                return response()->json([
                    'conversation' => ['id' => $existing->id],
                    'existing' => true,
                ]);
            }
        }

        $isGroup = $participantIds->count() > 1;

        $conversation = Conversation::create([
            'title' => $validated['title'] ?? null,
            'is_group' => $isGroup,
            'created_by' => $user->id,
        ]);

        // Attach all participants including the creator
        $conversation->users()->attach(
            $participantIds->push($user->id)->unique()->all()
        );

        return response()->json([
            'conversation' => ['id' => $conversation->id],
            'existing' => false,
        ], 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        if (! $conversation->isParticipant($request->user())) {
            abort(403);
        }

        $messages = $conversation->messages()
            ->with(['user:id,first_name,last_name', 'media'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(50);

        $items = [];

        /** @var Message $m */
        foreach ($messages->items() as $m) {
            $items[] = $this->formatMessage($m);
        }

        $participants = [];

        /** @var User $u */
        foreach ($conversation->users as $u) {
            $participants[] = [
                'id' => $u->id,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
                'avatar_thumb_url' => $u->avatarUrl('thumb'),
            ];
        }

        return response()->json([
            'messages' => $items,
            'next_cursor' => $messages->nextCursor()?->encode(),
            'has_more' => $messages->hasMorePages(),
            'participants' => $participants,
        ]);
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        if (! $conversation->isParticipant($request->user())) {
            abort(403);
        }

        $validated = $request->validate([
            'body' => 'nullable|string|max:5000',
            'attachments' => 'sometimes|array|max:10',
            'attachments.*' => 'file|max:10240', // 10 MB per file
        ]);

        $hasFiles = $request->hasFile('attachments');
        $hasBody = isset($validated['body']) && trim((string) $validated['body']) !== '';

        if (! $hasBody && ! $hasFiles) {
            abort(422, 'Message must include text or at least one attachment.');
        }

        /** @var Message $message */
        $message = DB::connection($conversation->getConnectionName())->transaction(function () use ($conversation, $validated, $request) {
            $message = $conversation->messages()->create([
                'user_id' => $request->user()->id,
                'body' => $validated['body'] ?? '',
            ]);

            $conversation->update(['last_message_at' => now()]);

            // Mark as read for the sender
            $conversation->users()->updateExistingPivot($request->user()->id, [
                'last_read_at' => now(),
            ]);

            $message->load('user:id,first_name,last_name');

            return $message;
        });

        if ($hasFiles) {
            /** @var UploadedFile $file */
            foreach ($request->file('attachments', []) as $file) {
                $message->addMedia($file)->toMediaCollection('attachments');
            }
            $message->load('media');
        }

        broadcast(new MessageSent($message, $request->user()))->toOthers();

        // Notify every other participant (database + broadcast, mail if enabled).
        $sender = $request->user();
        $recipients = $conversation->users()
            ->where('user_id', '!=', $sender->id)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new NewChatMessageNotification($message, $sender));
        }

        return response()->json([
            'message' => $this->formatMessage($message),
        ], 201);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        if (! $conversation->isParticipant($request->user())) {
            abort(403);
        }

        $conversation->users()->updateExistingPivot($request->user()->id, [
            'last_read_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Mark every conversation the user participates in as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = now();

        DB::connection(config('chat.connection', 'pgsql'))
            ->table('conversation_user')
            ->where('user_id', $user->id)
            ->update(['last_read_at' => $now, 'updated_at' => $now]);

        return response()->json(['ok' => true]);
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);

        $query = $request->input('q');
        $user = $request->user();
        // Escape LIKE/ILIKE wildcards so % and _ don't leak raw user input into the pattern.
        $escapedQuery = addcslashes($query, '%_\\');

        $connection = config('chat.connection', 'pgsql');
        $driver = DB::connection($connection)->getDriverName();
        $op = $driver === 'pgsql' ? 'ilike' : 'like';

        $usersQuery = User::on($connection)
            ->where('id', '!=', $user->id)
            ->notBanned()
            ->where(function ($q) use ($escapedQuery, $op) {
                $q->where('first_name', $op, "%{$escapedQuery}%")
                    ->orWhere('last_name', $op, "%{$escapedQuery}%");
            });

        // When tenancy is enabled and user is not admin, limit to users
        // who share at least one customer (company) with the searcher.
        if (config('tenancy.enabled') && ! $user->hasRole('Admin')) {
            $customerIds = $user->customers()->pluck('tenants.id');
            $usersQuery->whereHas('customers', function ($q) use ($customerIds) {
                $q->whereIn('tenants.id', $customerIds);
            });
        }

        $users = $usersQuery
            ->limit(10)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
                'full_name' => $u->fullName(),
                'avatar_thumb_url' => $u->avatarUrl('thumb'),
            ]);

        return response()->json(['users' => $users]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMessage(Message $message): array
    {
        $attachments = $message->getMedia('attachments')->map(function ($m) {
            $isImage = str_starts_with((string) $m->mime_type, 'image/');

            return [
                'id' => $m->id,
                'name' => $m->file_name,
                'size' => $m->size,
                'mime_type' => $m->mime_type,
                'is_image' => $isImage,
                'url' => $m->getUrl(),
                'thumb_url' => $isImage && $m->hasGeneratedConversion('thumb') ? $m->getUrl('thumb') : null,
            ];
        })->values()->all();

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'user_id' => $message->user_id,
            'user' => [
                'id' => $message->user->id,
                'first_name' => $message->user->first_name,
                'last_name' => $message->user->last_name,
                'avatar_thumb_url' => $message->user->avatarUrl('thumb'),
            ],
            'body' => $message->body,
            'type' => $message->type,
            'attachments' => $attachments,
            'created_at' => $message->created_at->toISOString(),
        ];
    }
}
