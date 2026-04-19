<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import Dialog from 'primevue/dialog';
import type { ChatConversation, ChatUser } from '@/composables/useChat';
import { useUnreadCounts } from '@/composables/useUnreadCounts';

const props = defineProps<{
    unreadCount: number;
    currentUserId: number;
}>();

const { t } = useI18n();
const toast = useToast();
const { setMessages, decrementMessages } = useUnreadCounts();
const open = ref(false);
const conversations = ref<ChatConversation[]>([]);
const loading = ref(false);
const activeFilter = ref<'all' | 'unread' | 'groups'>('all');
const searchQuery = ref('');
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const quickReplyTarget = ref<ChatConversation | null>(null);
const quickReplyBody = ref('');
const quickReplySending = ref(false);
const quickReplyVisible = computed({
    get: () => quickReplyTarget.value !== null,
    set: (v: boolean) => {
        if (!v) quickReplyTarget.value = null;
    },
});

function fetchConversations() {
    loading.value = true;
    const params = new URLSearchParams();
    if (activeFilter.value !== 'all') params.set('filter', activeFilter.value);
    if (searchQuery.value.trim()) params.set('q', searchQuery.value.trim());
    const qs = params.toString();

    fetch(`/chat/conversations-list${qs ? '?' + qs : ''}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then(r => r.json())
        .then(json => { conversations.value = json.conversations ?? []; })
        .finally(() => { loading.value = false; });
}

watch(open, (v) => {
    if (v) {
        activeFilter.value = 'all';
        searchQuery.value = '';
        fetchConversations();
    }
});

watch(activeFilter, () => {
    fetchConversations();
});

watch(searchQuery, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchConversations();
    }, 300);
});

function goToConversation(c: ChatConversation) {
    open.value = false;
    router.visit('/chat', { data: { conversation: c.id } });
}

function openFullChat() {
    open.value = false;
}

function markAllViewed() {
    fetch('/chat/read-all', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
    })
        .then(async (r) => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
        })
        .then(() => {
            conversations.value = conversations.value.map((c) => ({ ...c, unread_count: 0 }));
            setMessages(0);
            toast.add({ severity: 'success', summary: t('chat.all_marked_viewed'), life: 3000 });
        })
        .catch(() => {
            toast.add({ severity: 'error', summary: t('chat.send_failed'), life: 4000 });
        });
}

function openQuickReply(c: ChatConversation) {
    quickReplyBody.value = '';
    quickReplyTarget.value = c;
}

function sendQuickReply() {
    const target = quickReplyTarget.value;
    const body = quickReplyBody.value.trim();
    if (!target || !body) return;

    quickReplySending.value = true;

    fetch(`/chat/conversations/${target.id}/messages`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ body }),
    })
        .then(async (r) => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.json();
        })
        .then(() => {
            const prevUnread = target.unread_count ?? 0;
            if (prevUnread > 0) decrementMessages(prevUnread);
            const idx = conversations.value.findIndex((c) => c.id === target.id);
            if (idx !== -1) conversations.value[idx].unread_count = 0;
            quickReplyTarget.value = null;
        })
        .catch(() => {
            toast.add({ severity: 'error', summary: t('chat.send_failed'), life: 4000 });
        })
        .finally(() => {
            quickReplySending.value = false;
        });
}

function getCsrfToken(): string {
    return decodeURIComponent(document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
}

// ── Display helpers ────────────────────────────────────────────

function displayName(c: ChatConversation): string {
    if (c.title) return c.title;
    const others = c.participants.filter(p => p.id !== props.currentUserId);
    return others.map(p => `${p.first_name} ${p.last_name}`).join(', ') || t('chat.unknown_user');
}

function displayAvatar(c: ChatConversation): ChatUser | null {
    if (c.is_group) return null;
    return c.participants.find(p => p.id !== props.currentUserId) ?? null;
}

function initials(user: ChatUser): string {
    const first = (user.first_name?.trim() ?? '')[0] ?? '';
    const last = (user.last_name?.trim() ?? '')[0] ?? '';
    return (first + last).toUpperCase();
}

function previewText(c: ChatConversation): string {
    if (!c.latest_message) return '';
    const msg = c.latest_message;
    const prefix = msg.user_id === props.currentUserId ? t('chat.you') + ': ' : '';
    const body = msg.body.length > 50 ? msg.body.slice(0, 50) + '…' : msg.body;
    return prefix + body;
}

function timeLabel(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    const seconds = Math.floor((now.getTime() - d.getTime()) / 1000);
    if (seconds < 60) return t('notifications.just_now');
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d`;
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

const filters = ['all', 'unread', 'groups'] as const;
</script>

<template>
    <div class="relative">
        <!-- Toggle button -->
        <button
            @click="open = !open"
            class="relative p-2 rounded-lg transition-colors hover:bg-gray-100 dark:hover:bg-dark-800 cursor-pointer"
            :aria-label="t('chat.title')"
        >
            <i class="pi pi-comments text-lg text-gray-600 dark:text-gray-300"></i>
            <span
                v-if="unreadCount > 0"
                class="absolute -top-0.5 -right-0.5 min-w-5 h-5 px-1 rounded-full bg-indigo-500 text-white text-[10px] font-semibold flex items-center justify-center"
            >{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
        </button>

        <!-- Dropdown panel -->
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute right-0 mt-2 w-80 sm:w-[400px] rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700 shadow-lg z-50"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100 dark:border-dark-700">
                    <span class="text-sm font-semibold">{{ t('chat.title') }}</span>
                    <div class="flex items-center gap-3">
                        <button
                            v-if="unreadCount > 0"
                            type="button"
                            @click="markAllViewed"
                            class="text-xs text-indigo-500 hover:underline cursor-pointer"
                        >
                            {{ t('chat.mark_all_viewed') }}
                        </button>
                        <Link href="/chat" @click="openFullChat" class="text-xs text-indigo-500 hover:underline">
                            {{ t('chat.open_messages') }}
                        </Link>
                    </div>
                </div>

                <!-- Filter tabs -->
                <div class="flex gap-1 px-4 py-2 border-b border-gray-100 dark:border-dark-700">
                    <button
                        v-for="f in filters"
                        :key="f"
                        @click="activeFilter = f"
                        class="px-3 py-1 text-xs font-medium rounded-full transition-colors cursor-pointer"
                        :class="activeFilter === f
                            ? 'bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300'
                            : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-800'"
                    >
                        {{ t(`chat.filter_${f}`) }}
                    </button>
                </div>

                <!-- Search -->
                <div class="px-4 py-2 border-b border-gray-100 dark:border-dark-700">
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('chat.search_conversations')"
                        class="w-full text-xs rounded-lg border border-gray-200 dark:border-dark-600 bg-gray-50 dark:bg-dark-800 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-transparent"
                    />
                </div>

                <!-- Loading -->
                <div v-if="loading" class="px-4 py-8 text-center">
                    <i class="pi pi-spin pi-spinner text-gray-400"></i>
                </div>

                <!-- Empty state -->
                <div v-else-if="conversations.length === 0" class="px-4 py-8 text-center text-sm text-gray-400">
                    {{ t('chat.no_conversations') }}
                </div>

                <!-- Conversation list -->
                <ul v-else class="max-h-96 overflow-y-auto divide-y divide-gray-100 dark:divide-dark-800">
                    <li
                        v-for="c in conversations"
                        :key="c.id"
                        class="group flex items-center gap-3 px-4 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-dark-800/50"
                        :class="c.unread_count > 0 ? 'bg-indigo-50/30 dark:bg-dark-800/30' : ''"
                    >
                        <!-- Avatar -->
                        <div class="shrink-0 cursor-pointer" @click="goToConversation(c)">
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
                        <div class="flex-1 min-w-0 cursor-pointer" @click="goToConversation(c)">
                            <div class="flex justify-between items-baseline">
                                <p class="text-sm truncate" :class="c.unread_count > 0 ? 'font-semibold text-gray-900 dark:text-white' : 'font-medium text-gray-700 dark:text-gray-300'">
                                    {{ displayName(c) }}
                                </p>
                                <span class="text-[10px] text-gray-400 shrink-0 ml-2">{{ timeLabel(c.last_message_at) }}</span>
                            </div>
                            <p class="text-xs truncate" :class="c.unread_count > 0 ? 'text-gray-600 dark:text-gray-300' : 'text-gray-400 dark:text-gray-500'">
                                {{ previewText(c) }}
                            </p>
                        </div>

                        <!-- Quick reply -->
                        <button
                            type="button"
                            @click.stop="openQuickReply(c)"
                            class="opacity-0 group-hover:opacity-100 transition-opacity shrink-0 p-1.5 rounded-md text-indigo-500 hover:bg-indigo-50 dark:hover:bg-dark-800 cursor-pointer"
                            :title="t('chat.quick_reply')"
                            :aria-label="t('chat.quick_reply')"
                        >
                            <i class="pi pi-reply text-sm"></i>
                        </button>

                        <!-- Unread dot -->
                        <span v-if="c.unread_count > 0" class="w-2.5 h-2.5 rounded-full bg-indigo-500 shrink-0"></span>
                    </li>
                </ul>

                <!-- Footer -->
                <div class="px-4 py-2 border-t border-gray-100 dark:border-dark-700 text-center">
                    <Link href="/chat" @click="openFullChat" class="text-xs text-indigo-500 hover:underline font-medium">
                        {{ t('chat.open_messages') }}
                    </Link>
                </div>
            </div>
        </Transition>

        <!-- Click-outside backdrop -->
        <div v-if="open" @click="open = false" class="fixed inset-0 z-40"></div>

        <!-- Quick reply dialog -->
        <Dialog
            v-model:visible="quickReplyVisible"
            :modal="true"
            :draggable="false"
            :dismissable-mask="true"
            :close-on-escape="true"
            :style="{ width: 'min(420px, calc(100vw - 2rem))' }"
            :header="quickReplyTarget ? t('chat.quick_reply_to', { name: displayName(quickReplyTarget) }) : t('chat.quick_reply')"
        >
            <div class="space-y-3">
                <textarea
                    v-model="quickReplyBody"
                    rows="3"
                    class="w-full rounded-xl border border-gray-300 dark:border-dark-600 bg-white dark:bg-dark-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                    :placeholder="t('chat.reply_placeholder')"
                    @keydown.enter.exact.prevent="sendQuickReply"
                ></textarea>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        @click="quickReplyTarget = null"
                        class="px-3 py-1.5 text-sm rounded-lg text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-800 cursor-pointer"
                    >{{ t('common.cancel') }}</button>
                    <button
                        type="button"
                        :disabled="!quickReplyBody.trim() || quickReplySending"
                        @click="sendQuickReply"
                        class="px-4 py-1.5 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                    >{{ t('chat.send') }}</button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
