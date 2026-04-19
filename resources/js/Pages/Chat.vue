<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, ref, watch, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConversationList from '@/Components/Chat/ConversationList.vue';
import MessageThread from '@/Components/Chat/MessageThread.vue';
import MessageInput from '@/Components/Chat/MessageInput.vue';
import NewConversationDialog from '@/Components/Chat/NewConversationDialog.vue';
import { useChat } from '@/composables/useChat';
import { useCustomer } from '@/composables/useCustomer';
import type { ChatConversation, ChatMessage } from '@/composables/useChat';
import type { PageProps } from '@/types';

const props = defineProps<{
    conversations: ChatConversation[];
}>();

const { t } = useI18n();
const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user!);
const { customerUrl } = useCustomer();
const { messages: realtimeMessages, typingUser, joinConversation, whisperTyping } = useChat();

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

    // Reset unread for this conversation in the list
    const idx = conversationList.value.findIndex(c => c.id === conversation.id);
    if (idx !== -1) {
        conversationList.value[idx].unread_count = 0;
    }
}

function fetchMessages(conversationId: number, cursor?: string) {
    loadingMessages.value = true;
    const url = customerUrl(`/chat/conversations/${conversationId}`) + (cursor ? `?cursor=${cursor}` : '');

    fetch(url, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then(r => r.json())
        .then(json => {
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
        .finally(() => { loadingMessages.value = false; });
}

function loadMore() {
    if (!activeConversation.value || !nextCursor.value) return;
    fetchMessages(activeConversation.value.id, nextCursor.value);
}

function handleRealtimeMessage(msg: ChatMessage) {
    threadMessages.value.push(msg);
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

function sendMessage(body: string) {
    if (!activeConversation.value) return;

    fetch(customerUrl(`/chat/conversations/${activeConversation.value.id}/messages`), {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ body }),
    })
        .then(r => r.json())
        .then(json => {
            if (json.message) {
                threadMessages.value.push(json.message);

                // Update conversation list
                const cId = activeConversation.value!.id;
                const idx = conversationList.value.findIndex(c => c.id === cId);
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
            }
        });
}

function markRead(conversationId: number) {
    fetch(customerUrl(`/chat/conversations/${conversationId}/read`), {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
    });
}

function handleTyping() {
    if (!activeConversation.value) return;
    whisperTyping(activeConversation.value.id, user.value.full_name);
}

function createConversation(userIds: number[]) {
    fetch(customerUrl('/chat/conversations'), {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ user_ids: userIds }),
    })
        .then(r => r.json())
        .then(json => {
            showNewDialog.value = false;
            if (json.conversation?.id) {
                // Reload conversations and select the new one
                reloadConversations(json.conversation.id);
            }
        });
}

function reloadConversations(selectId?: number) {
    router.visit(customerUrl('/chat'), {
        preserveState: false,
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
