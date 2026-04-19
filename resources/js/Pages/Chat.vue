<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, ref, onBeforeUnmount, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConversationList from '@/Components/Chat/ConversationList.vue';
import MessageThread from '@/Components/Chat/MessageThread.vue';
import MessageInput from '@/Components/Chat/MessageInput.vue';
import NewConversationDialog from '@/Components/Chat/NewConversationDialog.vue';
import { useChat } from '@/composables/useChat';
import { useUnreadCounts } from '@/composables/useUnreadCounts';
import type { ChatConversation, ChatMessage } from '@/composables/useChat';
import type { PageProps } from '@/types';

const props = defineProps<{
    conversations: ChatConversation[];
}>();

function parseConversationParam(): number | null {
    const match = window.location.search.match(/[?&]conversation=(\d+)/);
    return match ? Number(match[1]) : null;
}

const { t } = useI18n();
const page = usePage<PageProps>();
const toast = useToast();
const user = computed(() => page.props.auth.user!);
const { typingUser, joinConversation, whisperTyping } = useChat();
const { decrementMessages, incrementMessages } = useUnreadCounts();

const conversationList = ref<ChatConversation[]>([...props.conversations]);
const activeConversation = ref<ChatConversation | null>(null);
const threadMessages = ref<ChatMessage[]>([]);
const loadingMessages = ref(false);
const hasMore = ref(false);
const nextCursor = ref<string | null>(null);
const showNewDialog = ref(false);
const mobileShowThread = ref(false);

function selectConversation(conversation: ChatConversation) {
    activeConversation.value = conversation;
    threadMessages.value = [];
    hasMore.value = false;
    nextCursor.value = null;
    mobileShowThread.value = true;

    fetchMessages(conversation.id);
    joinConversation(conversation.id, handleRealtimeMessage);
    markRead(conversation.id);

    // Reset unread for this conversation in the list and decrement the global badge.
    const idx = conversationList.value.findIndex(c => c.id === conversation.id);
    if (idx !== -1) {
        const wasUnread = conversationList.value[idx].unread_count ?? 0;
        conversationList.value[idx].unread_count = 0;
        if (wasUnread > 0) decrementMessages(wasUnread);
    }
}

let activeFetchId = 0;
let activeFetchAbort: AbortController | null = null;

function fetchMessages(conversationId: number, cursor?: string) {
    loadingMessages.value = true;
    activeFetchAbort?.abort();
    activeFetchAbort = new AbortController();
    const fetchId = ++activeFetchId;
    const controller = activeFetchAbort;
    const url = `/chat/conversations/${conversationId}` + (cursor ? `?cursor=${encodeURIComponent(cursor)}` : '');

    fetch(url, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal: controller.signal,
    })
        .then(async (r) => {
            if (!r.ok) {
                if (r.status === 403) throw new Error('forbidden');
                throw new Error(`HTTP ${r.status}`);
            }
            return r.json();
        })
        .then(json => {
            // Discard stale responses (user switched to another conversation).
            if (fetchId !== activeFetchId || activeConversation.value?.id !== conversationId) return;

            const fetched: ChatMessage[] = json.messages ?? [];
            // Messages come in desc order from API, reverse for display
            fetched.reverse();
            if (cursor) {
                threadMessages.value = [...fetched, ...threadMessages.value];
            } else {
                threadMessages.value = fetched;
            }
            nextCursor.value = json.next_cursor ?? null;
            hasMore.value = json.has_more ?? false;
        })
        .catch((err: unknown) => {
            if (err instanceof DOMException && err.name === 'AbortError') return;
            if (err instanceof Error && err.message === 'forbidden') {
                toast.add({ severity: 'warn', summary: t('chat.access_denied'), life: 5000 });
            } else {
                toast.add({ severity: 'error', summary: t('chat.load_failed'), life: 4000 });
            }
        })
        .finally(() => {
            if (controller.signal.aborted) return;
            if (fetchId === activeFetchId) loadingMessages.value = false;
        });
}

function loadMore() {
    if (!activeConversation.value || !nextCursor.value) return;
    fetchMessages(activeConversation.value.id, nextCursor.value);
}

function handleRealtimeMessage(msg: ChatMessage) {
    // Ignore stale events from a channel the user just left — the Echo
    // listener from a previous conversation can still fire briefly after
    // selecting a new one.
    if (activeConversation.value?.id !== msg.conversation_id) return;

    // Deduplicate — the sender already added the message from the POST response
    if (threadMessages.value.some(m => m.id === msg.id)) return;

    threadMessages.value.push(msg);
    // User is looking at this conversation, so mark it read immediately and
    // don't bump the global badge.
    markRead(msg.conversation_id);

    // Update conversation list
    const idx = conversationList.value.findIndex(c => c.id === msg.conversation_id);
    if (idx !== -1) {
        conversationList.value[idx].latest_message = {
            id: msg.id,
            body: msg.body,
            user_id: msg.user_id,
            user: { id: msg.user.id, first_name: msg.user.first_name },
            created_at: msg.created_at,
        };
        conversationList.value[idx].last_message_at = msg.created_at;
    }
}

function getSocketId(): string {
    return window.Echo?.socketId?.() ?? '';
}

function sendMessage(payload: { body: string; files: File[] }) {
    if (!activeConversation.value) return;

    // Capture the target at dispatch time so a user switching conversations
    // while the POST is in flight can't cause the response to leak into the
    // newly active conversation.
    const sentConversationId = activeConversation.value.id;

    const headers: Record<string, string> = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': getCsrfToken(),
    };
    const socketId = getSocketId();
    if (socketId) {
        headers['X-Socket-ID'] = socketId;
    }

    const form = new FormData();
    if (payload.body) form.append('body', payload.body);
    for (const file of payload.files) {
        form.append('attachments[]', file);
    }

    fetch(`/chat/conversations/${sentConversationId}/messages`, {
        method: 'POST',
        headers,
        body: form,
    })
        .then(async (r) => {
            if (!r.ok) {
                if (r.status === 403) throw new Error('forbidden');
                throw new Error(`HTTP ${r.status}`);
            }
            return r.json();
        })
        .then(json => {
            if (!json.message) return;

            // Only append to thread if the user is still on the same conversation.
            if (activeConversation.value?.id === sentConversationId) {
                threadMessages.value.push(json.message);
            }

            // Always update the conversation list entry — it stays accurate regardless of focus.
            const idx = conversationList.value.findIndex(c => c.id === sentConversationId);
            if (idx !== -1) {
                conversationList.value[idx].latest_message = {
                    id: json.message.id,
                    body: json.message.body,
                    user_id: json.message.user_id,
                    user: { id: json.message.user.id, first_name: json.message.user.first_name },
                    created_at: json.message.created_at,
                };
                conversationList.value[idx].last_message_at = json.message.created_at;
                // Move to top
                const conv = conversationList.value.splice(idx, 1)[0];
                conversationList.value.unshift(conv);
            }
        })
        .catch((err: unknown) => {
            if (err instanceof Error && err.message === 'forbidden') {
                toast.add({ severity: 'warn', summary: t('chat.access_denied'), life: 5000 });
            } else {
                toast.add({ severity: 'error', summary: t('chat.send_failed'), life: 4000 });
            }
        });
}

function markRead(conversationId: number) {
    fetch(`/chat/conversations/${conversationId}/read`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
    }).catch(() => {
        // Silent — read receipts are non-critical and retried on next open.
    });
}

const TYPING_THROTTLE_MS = 2000;
let lastTypingAt = 0;

function handleTyping() {
    if (!activeConversation.value) return;
    const now = Date.now();
    if (now - lastTypingAt < TYPING_THROTTLE_MS) return;
    lastTypingAt = now;
    whisperTyping(activeConversation.value.id, user.value.full_name);
}

function createConversation(userIds: number[]) {
    fetch('/chat/conversations', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ user_ids: userIds }),
    })
        .then(async (r) => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.json();
        })
        .then(json => {
            showNewDialog.value = false;
            if (json.conversation?.id) {
                // Reload conversations and select the new one
                reloadConversations(json.conversation.id);
            }
        })
        .catch(() => {
            toast.add({ severity: 'error', summary: t('chat.create_conversation_failed'), life: 4000 });
        });
}

onBeforeUnmount(() => {
    activeFetchAbort?.abort();
});

onMounted(() => {
    const id = parseConversationParam();
    if (id === null) return;
    const conv = conversationList.value.find((c) => c.id === id);
    if (conv) selectConversation(conv);
});

function reloadConversations(selectId?: number) {
    router.reload({
        only: ['conversations'],
        onSuccess: (page) => {
            const newConversations = (page.props as any).conversations ?? [];
            conversationList.value = newConversations;
            if (selectId) {
                const conv = conversationList.value.find((c: ChatConversation) => c.id === selectId);
                if (conv) selectConversation(conv);
            }
        },
    });
}

function goBackToList() {
    mobileShowThread.value = false;
    activeConversation.value = null;
}

function otherParticipantName(conversation: ChatConversation): string {
    if (conversation.title) return conversation.title;
    const others = conversation.participants.filter(p => p.id !== user.value.id);
    return others.map(p => `${p.first_name} ${p.last_name}`).join(', ');
}

function getCsrfToken(): string {
    return decodeURIComponent(document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
}
</script>

<template>
    <AppLayout>
        <Head :title="t('chat.title')" />
        <div class="max-w-6xl mx-auto px-0 sm:px-4 lg:px-8 py-0 sm:py-6">
            <div class="bg-white dark:bg-dark-900 sm:rounded-2xl sm:border border-gray-200 dark:border-dark-700 overflow-hidden flex" style="height: calc(100vh - 10rem);">
                <!-- Conversation list (hidden on mobile when thread is open) -->
                <div
                    class="w-full sm:w-80 lg:w-96 border-r border-gray-200 dark:border-dark-700 shrink-0"
                    :class="mobileShowThread ? 'hidden sm:flex sm:flex-col' : 'flex flex-col'"
                >
                    <ConversationList
                        :conversations="conversationList"
                        :active-id="activeConversation?.id ?? null"
                        :current-user-id="user.id"
                        @select="selectConversation"
                        @new-chat="showNewDialog = true"
                    />
                </div>

                <!-- Message thread (hidden on mobile when list is shown) -->
                <div
                    class="flex-1 flex flex-col min-w-0"
                    :class="!mobileShowThread ? 'hidden sm:flex' : 'flex'"
                >
                    <template v-if="activeConversation">
                        <!-- Thread header -->
                        <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-200 dark:border-dark-700">
                            <button
                                @click="goBackToList"
                                :aria-label="t('chat.back_to_conversations')"
                                class="sm:hidden p-1 rounded hover:bg-gray-100 dark:hover:bg-dark-800 cursor-pointer"
                            >
                                <i class="pi pi-arrow-left text-gray-500"></i>
                            </button>
                            <h3 class="text-sm font-semibold truncate">{{ otherParticipantName(activeConversation) }}</h3>
                        </div>

                        <MessageThread
                            :messages="threadMessages"
                            :current-user-id="user.id"
                            :has-more="hasMore"
                            :loading="loadingMessages"
                            :typing-user="typingUser"
                            @load-more="loadMore"
                        />

                        <MessageInput
                            @send="sendMessage"
                            @typing="handleTyping"
                        />
                    </template>

                    <!-- No conversation selected -->
                    <div v-else class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <i class="pi pi-comments text-4xl text-gray-300 dark:text-dark-600 mb-3"></i>
                            <p class="text-sm text-gray-400">{{ t('chat.select_conversation') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <NewConversationDialog
            v-model:visible="showNewDialog"
            @create="createConversation"
        />
    </AppLayout>
</template>
