<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('admin.health', function ($user) {
    return $user !== null && $user->hasRole('Admin');
});

Broadcast::channel('chat.conversation.{conversationId}', function ($user, $conversationId) {
    return $user->conversations()->whereKey((int) $conversationId)->exists();
});
