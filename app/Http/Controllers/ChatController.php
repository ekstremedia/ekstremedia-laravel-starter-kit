<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $conversations = [];

        foreach ($models as $c) {
            $latest = $c->latestMessage;

            $conversations[] = [
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
                'unread_count' => $c->unreadCountFor($user),
                'last_message_at' => $c->last_message_at?->toISOString(),
            ];
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

        $conversations = [];

        foreach ($models as $c) {
            $latest = $c->latestMessage;
            $unreadCount = $c->unreadCountFor($user);

            // Skip non-unread when filtering
            if ($filter === 'unread' && $unreadCount === 0) {
                continue;
            }

            $conversations[] = [
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

        return response()->json(['conversations' => $conversations]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|integer|exists:'.config('chat.connection', 'pgsql').'.users,id',
            'title' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $participantIds = collect($validated['user_ids'])->reject(fn ($id) => (int) $id === $user->id);

        if ($participantIds->isEmpty()) {
            return response()->json(['message' => 'At least one other participant is required.'], 422);
        }

        // For 1:1 chats, check if a conversation already exists
        if ($participantIds->count() === 1 && ! isset($validated['title'])) {
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
            ->with('user:id,first_name,last_name')
            ->orderByDesc('created_at')
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
            'body' => 'required|string|max:5000',
        ]);

        /** @var Message $message */
        $message = DB::connection($conversation->getConnectionName())->transaction(function () use ($conversation, $validated, $request) {
            $message = $conversation->messages()->create([
                'user_id' => $request->user()->id,
                'body' => $validated['body'],
            ]);

            $conversation->update(['last_message_at' => now()]);

            // Mark as read for the sender
            $conversation->users()->updateExistingPivot($request->user()->id, [
                'last_read_at' => now(),
            ]);

            $message->load('user:id,first_name,last_name');

            return $message;
        });

        broadcast(new MessageSent($message, $request->user()))->toOthers();

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

    public function searchUsers(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:1|max:100']);

        $query = $request->input('q');
        $user = $request->user();
        $escapedQuery = str_replace(['%', '_'], ['\%', '\_'], $query);

        $connection = config('chat.connection', 'pgsql');
        $driver = DB::connection($connection)->getDriverName();
        $op = $driver === 'pgsql' ? 'ilike' : 'like';

        $usersQuery = User::on($connection)
            ->where('id', '!=', $user->id)
            ->notBanned()
            ->where(function ($q) use ($escapedQuery, $op) {
                $q->where('first_name', $op, "%{$escapedQuery}%")
                    ->orWhere('last_name', $op, "%{$escapedQuery}%")
                    ->orWhere('email', $op, "%{$escapedQuery}%");
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
            'created_at' => $message->created_at->toISOString(),
        ];
    }
}
