<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { ref, nextTick, watch, onMounted } from 'vue';
import type { ChatMessage } from '@/composables/useChat';

const props = defineProps<{
    messages: ChatMessage[];
    currentUserId: number;
    hasMore: boolean;
    loading: boolean;
    typingUser: string | null;
}>();

const emit = defineEmits<{
    loadMore: [];
}>();

const { t } = useI18n();
const scrollContainer = ref<HTMLElement | null>(null);
const autoScroll = ref(true);

function scrollToBottom() {
    nextTick(() => {
        if (scrollContainer.value && autoScroll.value) {
            scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
        }
    });
}

function handleScroll() {
    if (!scrollContainer.value) return;
    const { scrollTop, scrollHeight, clientHeight } = scrollContainer.value;
    autoScroll.value = scrollHeight - scrollTop - clientHeight < 50;

    // Load more when scrolled near top
    if (scrollTop < 100 && props.hasMore && !props.loading) {
        emit('loadMore');
    }
}

watch(() => props.messages.length, () => {
    scrollToBottom();
});

onMounted(() => {
    scrollToBottom();
});

function isSameDay(a: string, b: string): boolean {
    return new Date(a).toDateString() === new Date(b).toDateString();
}

function dateLabel(iso: string): string {
    const d = new Date(iso);
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);

    if (d.toDateString() === today.toDateString()) return t('chat.today');
    if (d.toDateString() === yesterday.toDateString()) return t('chat.yesterday');
    return d.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' });
}

function timeLabel(iso: string): string {
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function initials(user: { first_name: string; last_name: string }): string {
    const first = (user.first_name?.trim() ?? '')[0] ?? '';
    const last = (user.last_name?.trim() ?? '')[0] ?? '';
    return (first + last).toUpperCase();
}

defineExpose({ scrollToBottom });
</script>

<template>
    <div ref="scrollContainer" @scroll="handleScroll" class="flex-1 overflow-y-auto px-4 py-4 space-y-1">
        <!-- Load more spinner -->
        <div v-if="loading && hasMore" class="text-center py-2">
            <i class="pi pi-spin pi-spinner text-gray-400"></i>
        </div>

        <template v-for="(msg, i) in messages" :key="msg.id">
            <!-- Date separator -->
            <div
                v-if="i === 0 || !isSameDay(messages[i - 1].created_at, msg.created_at)"
                class="flex items-center gap-3 py-3"
            >
                <div class="flex-1 h-px bg-gray-200 dark:bg-dark-700"></div>
                <span class="text-[10px] text-gray-400 font-medium uppercase">{{ dateLabel(msg.created_at) }}</span>
                <div class="flex-1 h-px bg-gray-200 dark:bg-dark-700"></div>
            </div>

            <!-- Message bubble -->
            <div
                class="flex gap-2"
                :class="msg.user_id === currentUserId ? 'justify-end' : 'justify-start'"
            >
                <!-- Other user avatar -->
                <div v-if="msg.user_id !== currentUserId" class="shrink-0 mt-auto">
                    <img
                        v-if="msg.user.avatar_thumb_url"
                        :src="msg.user.avatar_thumb_url"
                        class="w-7 h-7 rounded-full object-cover"
                    />
                    <div
                        v-else
                        class="w-7 h-7 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[10px] font-semibold"
                    >{{ initials(msg.user) }}</div>
                </div>

                <div
                    class="max-w-[75%] px-3 py-2 rounded-2xl text-sm"
                    :class="msg.user_id === currentUserId
                        ? 'bg-indigo-600 text-white rounded-br-md'
                        : 'bg-gray-100 dark:bg-dark-800 text-gray-900 dark:text-gray-100 rounded-bl-md'"
                >
                    <p class="whitespace-pre-wrap break-words">{{ msg.body }}</p>
                    <p class="text-[10px] mt-1"
                       :class="msg.user_id === currentUserId ? 'text-indigo-200' : 'text-gray-400'">
                        {{ timeLabel(msg.created_at) }}
                    </p>
                </div>
            </div>
        </template>

        <!-- Typing indicator -->
        <div v-if="typingUser" class="flex items-center gap-2 py-1">
            <span class="text-xs text-gray-400 italic">{{ t('chat.typing', { name: typingUser }) }}</span>
        </div>

        <!-- Empty state -->
        <div v-if="messages.length === 0 && !loading" class="flex items-center justify-center h-full">
            <p class="text-sm text-gray-400">{{ t('chat.no_messages') }}</p>
        </div>
    </div>
</template>
