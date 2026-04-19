<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { ChatUser } from '@/composables/useChat';

const props = defineProps<{
    visible: boolean;
}>();

const emit = defineEmits<{
    'update:visible': [value: boolean];
    create: [userIds: number[]];
}>();

const { t } = useI18n();

const query = ref('');
const results = ref<(ChatUser & { full_name: string })[]>([]);
const searching = ref(false);
const selectedUsers = ref<(ChatUser & { full_name: string })[]>([]);
const searchInput = ref<HTMLInputElement | null>(null);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
let searchAbort: AbortController | null = null;
let lastFocused: HTMLElement | null = null;

watch(query, (val) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchAbort?.abort();
    if (val.trim().length < 2) {
        results.value = [];
        searching.value = false;
        return;
    }
    searching.value = true;
    searchTimeout = setTimeout(() => {
        searchAbort = new AbortController();
        const controller = searchAbort;
        fetch(`/chat/users/search?q=${encodeURIComponent(val.trim())}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
        })
            .then(r => r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`)))
            .then(json => {
                if (controller.signal.aborted || !props.visible) return;
                results.value = (json.users ?? []).filter(
                    (u: ChatUser) => !selectedUsers.value.some(s => s.id === u.id),
                );
            })
            .catch(() => { /* aborted or network error */ })
            .finally(() => {
                if (!controller.signal.aborted) searching.value = false;
            });
    }, 300);
});

watch(() => props.visible, (v) => {
    if (v) {
        lastFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        nextTick(() => searchInput.value?.focus());
    } else {
        if (searchTimeout) clearTimeout(searchTimeout);
        searchAbort?.abort();
        query.value = '';
        results.value = [];
        selectedUsers.value = [];
        searching.value = false;
        lastFocused?.focus();
        lastFocused = null;
    }
});

function onKeydown(e: KeyboardEvent) {
    if (props.visible && e.key === 'Escape') {
        e.preventDefault();
        close();
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    if (searchTimeout) clearTimeout(searchTimeout);
    searchAbort?.abort();
});

function selectUser(user: ChatUser & { full_name: string }) {
    selectedUsers.value.push(user);
    results.value = results.value.filter(r => r.id !== user.id);
    query.value = '';
}

function removeUser(userId: number) {
    selectedUsers.value = selectedUsers.value.filter(u => u.id !== userId);
}

function submit() {
    if (selectedUsers.value.length === 0) return;
    emit('create', selectedUsers.value.map(u => u.id));
}

function close() {
    emit('update:visible', false);
}

function initials(user: ChatUser): string {
    const first = (user.first_name?.trim() ?? '')[0] ?? '';
    const last = (user.last_name?.trim() ?? '')[0] ?? '';
    return (first + last).toUpperCase();
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="visible"
                role="dialog"
                aria-modal="true"
                aria-labelledby="new-conversation-title"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/50" @click="close"></div>

                <!-- Dialog -->
                <div class="relative w-full max-w-md rounded-2xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700 shadow-xl">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-dark-700">
                        <h3 id="new-conversation-title" class="text-lg font-semibold">{{ t('chat.new_conversation') }}</h3>
                        <button @click="close" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-dark-800 cursor-pointer">
                            <i class="pi pi-times text-gray-400"></i>
                        </button>
                    </div>

                    <div class="p-5 space-y-4">
                        <!-- Selected users chips -->
                        <div v-if="selectedUsers.length > 0" class="flex flex-wrap gap-2">
                            <span
                                v-for="u in selectedUsers"
                                :key="u.id"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-xs font-medium"
                            >
                                {{ u.full_name }}
                                <button @click="removeUser(u.id)" class="hover:text-red-500 cursor-pointer">
                                    <i class="pi pi-times text-[10px]"></i>
                                </button>
                            </span>
                        </div>

                        <!-- Search input -->
                        <input
                            ref="searchInput"
                            v-model="query"
                            type="text"
                            :placeholder="t('chat.search_users')"
                            class="w-full rounded-xl border border-gray-300 dark:border-dark-600 bg-white dark:bg-dark-800 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />

                        <!-- Search results -->
                        <div v-if="searching" class="text-center py-3">
                            <i class="pi pi-spin pi-spinner text-gray-400"></i>
                        </div>
                        <ul
                            v-else-if="results.length > 0"
                            role="listbox"
                            :aria-label="t('chat.search_users')"
                            class="max-h-48 overflow-y-auto divide-y divide-gray-100 dark:divide-dark-800 rounded-xl border border-gray-200 dark:border-dark-700"
                        >
                            <li
                                v-for="u in results"
                                :key="u.id"
                                role="option"
                                tabindex="0"
                                @click="selectUser(u)"
                                @keydown.enter.prevent="selectUser(u)"
                                @keydown.space.prevent="selectUser(u)"
                                class="flex items-center gap-3 px-3 py-2.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-dark-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500"
                            >
                                <img
                                    v-if="u.avatar_thumb_url"
                                    :src="u.avatar_thumb_url"
                                    :alt="t('chat.avatar_alt', { name: u.full_name })"
                                    class="w-8 h-8 rounded-full object-cover"
                                />
                                <div
                                    v-else
                                    class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs font-semibold"
                                    :aria-label="t('chat.avatar_alt', { name: u.full_name })"
                                >
                                    {{ initials(u) }}
                                </div>
                                <span class="text-sm">{{ u.full_name }}</span>
                            </li>
                        </ul>
                        <p v-else-if="query.trim().length >= 2 && !searching" class="text-sm text-gray-400 text-center py-3">
                            {{ t('chat.no_users_found') }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-200 dark:border-dark-700">
                        <button @click="close" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-800 rounded-lg cursor-pointer">
                            {{ t('common.cancel') }}
                        </button>
                        <button
                            @click="submit"
                            :disabled="selectedUsers.length === 0"
                            class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
                        >
                            {{ t('chat.start_chat') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
