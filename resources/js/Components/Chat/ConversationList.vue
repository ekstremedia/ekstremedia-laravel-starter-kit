<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import type { ChatConversation, ChatUser } from '@/composables/useChat';

const props = defineProps<{
    conversations: ChatConversation[];
    activeId: number | null;
    currentUserId: number;
}>();

const emit = defineEmits<{
    select: [conversation: ChatConversation];
    newChat: [];
}>();

const { t } = useI18n();

function displayName(conversation: ChatConversation): string {
    if (conversation.title) return conversation.title;
    const others = conversation.participants.filter(p => p.id !== props.currentUserId);
    return others.map(p => `${p.first_name} ${p.last_name}`).join(', ') || t('chat.unknown_user');
}

function displayAvatar(conversation: ChatConversation): ChatUser | null {
    if (conversation.is_group) return null;
    return conversation.participants.find(p => p.id !== props.currentUserId) ?? null;
}

function initials(user: ChatUser): string {
    const first = (user.first_name?.trim() ?? '')[0] ?? '';
    const last = (user.last_name?.trim() ?? '')[0] ?? '';
    return (first + last).toUpperCase();
}

function previewText(conversation: ChatConversation): string {
    if (!conversation.latest_message) return t('chat.no_messages');
    const msg = conversation.latest_message;
    const prefix = msg.user_id === props.currentUserId ? t('chat.you') + ': ' : '';
    const body = msg.body.length > 60 ? msg.body.slice(0, 60) + '…' : msg.body;
    return prefix + body;
}

function timeLabel(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    const diff = now.getTime() - d.getTime();
    if (diff < 86400000) {
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    if (diff < 604800000) {
        return d.toLocaleDateString([], { weekday: 'short' });
    }
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-dark-700">
            <h2 class="text-lg font-semibold">{{ t('chat.title') }}</h2>
            <button
                @click="emit('newChat')"
                class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-dark-800 transition-colors cursor-pointer"
                :title="t('chat.new_conversation')"
            >
                <i class="pi pi-pen-to-square text-indigo-500"></i>
            </button>
        </div>

        <!-- List -->
        <div v-if="conversations.length === 0" class="flex-1 flex items-center justify-center px-4">
            <p class="text-sm text-gray-400">{{ t('chat.no_conversations') }}</p>
        </div>
        <ul v-else class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-dark-800">
            <li
                v-for="c in conversations"
                :key="c.id"
                @click="emit('select', c)"
                class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-dark-800/50"
                :class="activeId === c.id ? 'bg-indigo-50 dark:bg-indigo-500/10' : ''"
            >
                <!-- Avatar -->
                <div class="relative shrink-0">
                    <template v-if="displayAvatar(c)">
                        <img
                            v-if="displayAvatar(c)!.avatar_thumb_url"
                            :src="displayAvatar(c)!.avatar_thumb_url!"
                            class="w-10 h-10 rounded-full object-cover"
                        />
                        <div
                            v-else
                            class="w-10 h-10 rounded-full bg-indigo-600 text-white flex items-center justify-center text-sm font-semibold"
                        >{{ initials(displayAvatar(c)!) }}</div>
                    </template>
                    <div
                        v-else
                        class="w-10 h-10 rounded-full bg-gray-300 dark:bg-dark-600 flex items-center justify-center"
                    >
                        <i class="pi pi-users text-sm text-gray-600 dark:text-gray-300"></i>
                    </div>
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-baseline">
                        <p class="text-sm font-medium truncate" :class="c.unread_count > 0 ? 'text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300'">
                            {{ displayName(c) }}
                        </p>
                        <span class="text-[10px] text-gray-400 shrink-0 ml-2">{{ timeLabel(c.last_message_at) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <p class="text-xs truncate" :class="c.unread_count > 0 ? 'text-gray-600 dark:text-gray-300 font-medium' : 'text-gray-400 dark:text-gray-500'">
                            {{ previewText(c) }}
                        </p>
                        <span
                            v-if="c.unread_count > 0"
                            class="shrink-0 min-w-5 h-5 px-1 rounded-full bg-indigo-500 text-white text-[10px] font-semibold flex items-center justify-center"
                        >{{ c.unread_count > 9 ? '9+' : c.unread_count }}</span>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>
