<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('admin.health', function ($user) {
    return $user !== null && $user->hasRole('Admin');
});

Broadcast::channel('chat.conversation.{conversationId}', function ($user, $conversationId) {
    return Conversation::where('id', $conversationId)
        ->whereHas('users', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});
