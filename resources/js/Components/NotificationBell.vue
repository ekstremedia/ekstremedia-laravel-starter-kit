<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useCustomer } from '@/composables/useCustomer';
import { useUnreadCounts } from '@/composables/useUnreadCounts';

interface NotificationItem {
    id: string;
    type: string;
    data: { title?: string; message?: string; icon?: string };
    read_at: string | null;
    created_at: string;
}

const { t } = useI18n();
const { customerUrl } = useCustomer();
const { notificationsCount: unreadCount, decrementNotifications, setNotifications } = useUnreadCounts();

const open = ref(false);
const notifications = ref<NotificationItem[]>([]);
const loading = ref(false);

const notificationsUrl = computed(() => customerUrl('/notifications'));
const readAllUrl = computed(() => customerUrl('/notifications/read-all'));
const clearAllUrl = computed(() => customerUrl('/notifications'));

function fetchNotifications() {
    loading.value = true;
    fetch(notificationsUrl.value, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then(r => r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`)))
        .then(json => { notifications.value = json.recent ?? []; })
        .catch(() => { /* silent */ })
        .finally(() => { loading.value = false; });
}

watch(open, (v) => {
    if (v) fetchNotifications();
});

defineExpose({ refresh: fetchNotifications });

function markOneRead(n: NotificationItem) {
    if (n.read_at) return;
    router.post(customerUrl(`/notifications/${n.id}/read`), {}, {
        preserveScroll: true,
        onSuccess: () => {
            n.read_at = new Date().toISOString();
            decrementNotifications(1);
        },
    });
}

function deleteOne(n: NotificationItem) {
    const wasUnread = !n.read_at;
    router.delete(customerUrl(`/notifications/${n.id}`), {
        preserveScroll: true,
        onSuccess: () => {
            notifications.value = notifications.value.filter(x => x.id !== n.id);
            if (wasUnread) decrementNotifications(1);
        },
    });
}

function clearAll() {
    router.delete(clearAllUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            notifications.value = [];
            setNotifications(0);
            open.value = false;
        },
    });
}

function markAllRead() {
    router.post(readAllUrl.value, {}, {
        preserveScroll: true,
        onSuccess: () => {
            notifications.value.forEach(n => { n.read_at = n.read_at ?? new Date().toISOString(); });
            setNotifications(0);
            open.value = false;
        },
    });
}

function timeAgo(iso: string): string {
    const seconds = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (seconds < 60) return t('notifications.just_now');
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return t('notifications.minutes_ago', { n: minutes });
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return t('notifications.hours_ago', { n: hours });
    const days = Math.floor(hours / 24);
    return t('notifications.days_ago', { n: days });
}
</script>

<template>
    <div class="relative">
        <button
            type="button"
            @click="open = !open"
            class="relative p-2 rounded-lg transition-colors hover:bg-gray-100 dark:hover:bg-dark-800 cursor-pointer"
            :aria-label="t('notifications.title')"
        >
            <i class="pi pi-bell text-lg text-gray-600 dark:text-gray-300"></i>
            <span
                v-if="unreadCount > 0"
                class="absolute -top-0.5 -right-0.5 min-w-5 h-5 px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold flex items-center justify-center"
            >{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
        </button>
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
                class="absolute right-0 mt-2 w-80 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700 shadow-lg py-2 z-50"
            >
                <div class="flex items-center justify-between px-4 pb-2 border-b border-gray-100 dark:border-dark-700">
                    <span class="text-sm font-medium">{{ t('notifications.title') }}</span>
                    <div v-if="notifications.length > 0" class="flex gap-2">
                        <button v-if="unreadCount > 0" @click="markAllRead" class="text-xs text-indigo-500 hover:underline cursor-pointer">{{ t('notifications.mark_all_read') }}</button>
                        <button @click="clearAll" class="text-xs text-red-400 hover:underline cursor-pointer">{{ t('notifications.clear_all') }}</button>
                    </div>
                </div>
                <div v-if="loading" class="px-4 py-6 text-center">
                    <i class="pi pi-spin pi-spinner text-gray-400"></i>
                </div>
                <div v-else-if="notifications.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">
                    {{ t('notifications.empty') }}
                </div>
                <ul v-else class="max-h-80 overflow-y-auto divide-y divide-gray-100 dark:divide-dark-800">
                    <li v-for="n in notifications" :key="n.id"
                        class="group px-4 py-3 flex items-start gap-3 transition-colors"
                        :class="n.read_at ? 'opacity-60' : 'bg-indigo-50/50 dark:bg-dark-800/50'">
                        <i :class="n.data.icon ? `pi ${n.data.icon}` : 'pi pi-bell'"
                           class="mt-0.5 text-sm text-indigo-500"></i>
                        <div class="flex-1 min-w-0 cursor-pointer" @click="markOneRead(n)">
                            <p class="text-sm font-medium truncate">{{ n.data.title ?? n.type }}</p>
                            <p v-if="n.data.message" class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ n.data.message }}</p>
                            <p class="text-[10px] text-gray-400 mt-1">{{ timeAgo(n.created_at) }}</p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0 mt-0.5">
                            <span v-if="!n.read_at" class="w-2 h-2 rounded-full bg-indigo-500"></span>
                            <button @click.stop="deleteOne(n)" class="opacity-0 group-hover:opacity-100 p-1 text-gray-400 hover:text-red-500 transition-opacity cursor-pointer" :aria-label="t('notifications.delete')">
                                <i class="pi pi-times text-xs"></i>
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </Transition>
        <div v-if="open" @click="open = false" class="fixed inset-0 z-40"></div>
    </div>
</template>
