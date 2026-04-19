<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { usePage, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Toast from 'primevue/toast';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import DarkModeToggle from '@/Components/DarkModeToggle.vue';
import { useFlashToast } from '@/composables/useFlashToast';
import { useCustomer } from '@/composables/useCustomer';
import type { PageProps } from '@/types';

const { t } = useI18n();
const page = usePage<PageProps>();
const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => user.value?.roles?.includes('Admin') ?? false);
const appName = import.meta.env.VITE_APP_NAME || t('app.name');
const { customer, tenancyEnabled, customerUrl } = useCustomer();
const dashboardUrl = computed(() => customerUrl('/dashboard'));
const profileUrl = computed(() => customerUrl('/profile'));
const chatUrl = '/chat';
const notificationSettingsUrl = '/settings/notifications';
const notificationsReadAllUrl = computed(() => customerUrl('/notifications/read-all'));
const chatEnabled = computed(() => page.props.chat?.enabled ?? false);
const unreadMessagesCount = computed(() => user.value?.unread_messages_count ?? 0);
// Customer-scoped nav entries show in the layout when either tenancy is off
// (single-tenant mode, routes at root) or a customer is actively in scope.
const showCustomerNav = computed(() => !tenancyEnabled.value || Boolean(customer.value));

const dropdownOpen = ref(false);
const notificationsOpen = ref(false);
useFlashToast();

const unreadCount = computed(() => user.value?.unread_notifications_count ?? 0);

// ── Notification inbox ──────────────────────────────────────────
interface NotificationItem {
    id: string;
    type: string;
    data: { title?: string; message?: string; icon?: string };
    read_at: string | null;
    created_at: string;
}
const notifications = ref<NotificationItem[]>([]);
const loadingNotifications = ref(false);

const notificationsUrl = computed(() => customerUrl('/notifications'));

function fetchNotifications() {
    loadingNotifications.value = true;
    fetch(notificationsUrl.value, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then(r => r.json())
        .then(json => { notifications.value = json.recent ?? []; })
        .finally(() => { loadingNotifications.value = false; });
}

watch(notificationsOpen, (open) => {
    if (open) fetchNotifications();
});

function markOneRead(n: NotificationItem) {
    if (n.read_at) return;
    router.post(customerUrl(`/notifications/${n.id}/read`), {}, {
        preserveScroll: true,
        onSuccess: () => {
            n.read_at = new Date().toISOString();
        },
    });
}

function deleteOne(n: NotificationItem) {
    router.delete(customerUrl(`/notifications/${n.id}`), {
        preserveScroll: true,
        onSuccess: () => {
            notifications.value = notifications.value.filter(x => x.id !== n.id);
        },
    });
}

function clearAll() {
    if (!showCustomerNav.value) return;
    router.delete(customerUrl('/notifications'), {
        preserveScroll: true,
        onSuccess: () => {
            notifications.value = [];
            notificationsOpen.value = false;
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
const isImpersonating = computed(() => user.value?.is_impersonating ?? false);
const announcement = computed(() => (page.props as any).app_settings?.announcement as { text: string; severity: string } | null);
const announcementClass: Record<string, string> = {
    info: 'bg-sky-500/90',
    warn: 'bg-amber-500/90',
    danger: 'bg-rose-500/90',
    success: 'bg-emerald-500/90',
};

function logout() {
    router.post('/logout');
}

function leaveImpersonation() {
    router.post('/impersonate/leave');
}

function markAllRead() {
    if (!showCustomerNav.value) {
        return;
    }

    router.post(notificationsReadAllUrl.value, {}, {
        preserveScroll: true,
        onSuccess: () => {
            notifications.value.forEach(n => { n.read_at = n.read_at ?? new Date().toISOString(); });
        },
    });
    notificationsOpen.value = false;
}

function initials(u: { first_name: string; last_name: string }) {
    const first = (u.first_name?.trim() ?? '')[0] ?? '';
    const last = (u.last_name?.trim() ?? '')[0] ?? '';
    return (first + last).toUpperCase();
}
</script>

<template>
    <Toast position="top-right" />
    <div class="min-h-screen bg-gray-50 dark:bg-dark-950 text-gray-900 dark:text-gray-100 transition-colors">
        <!-- Announcement banner -->
        <div v-if="announcement"
             :class="[announcementClass[announcement.severity] ?? 'bg-sky-500/90', 'text-white px-4 py-2 text-sm text-center']">
            <i class="pi pi-megaphone mr-2"></i>{{ announcement.text }}
        </div>

        <!-- Impersonation banner -->
        <div v-if="isImpersonating" class="bg-amber-500/90 text-white px-4 py-2 text-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div class="truncate"><i class="pi pi-user-edit mr-2"></i>{{ t('impersonation.banner') }} <strong>{{ user?.email }}</strong>.</div>
            <button @click="leaveImpersonation" class="self-start sm:self-auto px-3 py-1 rounded bg-white/20 hover:bg-white/30 text-sm cursor-pointer">
                <i class="pi pi-sign-out mr-1"></i>{{ t('impersonation.stop') }}
            </button>
        </div>
        <!-- Navigation -->
        <nav class="border-b border-gray-200 dark:border-dark-800 bg-white dark:bg-dark-900 transition-colors">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center gap-2">
                    <!-- Left: Logo + nav links -->
                    <div class="flex items-center gap-4 md:gap-8 min-w-0">
                        <Link href="/" class="text-base sm:text-xl font-bold text-indigo-600 dark:text-indigo-400 transition-colors hover:text-indigo-500 truncate">
                            {{ appName }}
                        </Link>

                        <template v-if="user">
                            <div class="hidden sm:flex items-center gap-1">
                                <Link
                                    v-if="showCustomerNav"
                                    :href="dashboardUrl"
                                    class="px-3 py-2 text-sm font-medium rounded-lg transition-colors"
                                    :class="$page.url.startsWith(dashboardUrl)
                                        ? 'text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-500/10'
                                        : 'text-gray-600 dark:text-dark-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-dark-800'"
                                >
                                    {{ t('nav.dashboard') }}
                                </Link>
                                <Link
                                    v-if="isAdmin"
                                    href="/admin"
                                    class="px-3 py-2 text-sm font-medium rounded-lg transition-colors"
                                    :class="$page.url.startsWith('/admin')
                                        ? 'text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-500/10'
                                        : 'text-gray-600 dark:text-dark-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-dark-800'"
                                >
                                    {{ t('nav.admin') }}
                                </Link>
                            </div>
                        </template>
                    </div>

                    <!-- Right side -->
                    <div class="flex items-center gap-1 sm:gap-3">
                        <div class="hidden sm:block"><LanguageSwitcher /></div>
                        <DarkModeToggle />

                        <!-- Notification bell -->
                        <template v-if="user">
                            <div class="relative">
                                <button
                                    @click="notificationsOpen = !notificationsOpen"
                                    class="relative p-2 rounded-lg transition-colors hover:bg-gray-100 dark:hover:bg-dark-800 cursor-pointer"
                                    aria-label="Notifications"
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
                                        v-if="notificationsOpen"
                                        class="absolute right-0 mt-2 w-80 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700 shadow-lg py-2 z-50"
                                    >
                                        <div class="flex items-center justify-between px-4 pb-2 border-b border-gray-100 dark:border-dark-700">
                                            <span class="text-sm font-medium">{{ t('notifications.title') }}</span>
                                            <div v-if="notifications.length > 0" class="flex gap-2">
                                                <button v-if="unreadCount > 0" @click="markAllRead" class="text-xs text-indigo-500 hover:underline cursor-pointer">{{ t('notifications.mark_all_read') }}</button>
                                                <button @click="clearAll" class="text-xs text-red-400 hover:underline cursor-pointer">{{ t('notifications.clear_all') }}</button>
                                            </div>
                                        </div>
                                        <div v-if="loadingNotifications" class="px-4 py-6 text-center">
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
                                                    <button @click.stop="deleteOne(n)" class="opacity-0 group-hover:opacity-100 p-1 text-gray-400 hover:text-red-500 transition-opacity cursor-pointer" aria-label="Delete notification">
                                                        <i class="pi pi-times text-xs"></i>
                                                    </button>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </Transition>
                                <div v-if="notificationsOpen" @click="notificationsOpen = false" class="fixed inset-0 z-40"></div>
                            </div>
                        </template>

                        <!-- Chat messages -->
                        <template v-if="user && chatEnabled">
                            <Link
                                :href="chatUrl"
                                class="relative p-2 rounded-lg transition-colors hover:bg-gray-100 dark:hover:bg-dark-800"
                                :aria-label="t('chat.title')"
                            >
                                <i class="pi pi-comments text-lg text-gray-600 dark:text-gray-300"></i>
                                <span
                                    v-if="unreadMessagesCount > 0"
                                    class="absolute -top-0.5 -right-0.5 min-w-5 h-5 px-1 rounded-full bg-indigo-500 text-white text-[10px] font-semibold flex items-center justify-center"
                                >{{ unreadMessagesCount > 99 ? '99+' : unreadMessagesCount }}</span>
                            </Link>
                        </template>

                        <!-- User dropdown -->
                        <template v-if="user">
                            <div class="relative">
                                <button
                                    @click="dropdownOpen = !dropdownOpen"
                                    class="flex items-center gap-2 cursor-pointer rounded-lg px-2 py-1.5 transition-colors hover:bg-gray-100 dark:hover:bg-dark-800"
                                >
                                    <img
                                        v-if="user.avatar_thumb_url"
                                        :src="user.avatar_thumb_url"
                                        :alt="user.full_name"
                                        class="w-8 h-8 rounded-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs font-semibold"
                                    >
                                        {{ initials(user) }}
                                    </div>
                                    <span class="text-sm text-gray-700 dark:text-gray-300 hidden sm:inline">
                                        {{ user.full_name }}
                                    </span>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <!-- Dropdown menu -->
                                <Transition
                                    enter-active-class="transition ease-out duration-100"
                                    enter-from-class="opacity-0 scale-95"
                                    enter-to-class="opacity-100 scale-100"
                                    leave-active-class="transition ease-in duration-75"
                                    leave-from-class="opacity-100 scale-100"
                                    leave-to-class="opacity-0 scale-95"
                                >
                                    <div
                                        v-if="dropdownOpen"
                                        @click="dropdownOpen = false"
                                        class="absolute right-0 mt-2 w-48 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700 shadow-lg dark:shadow-dark-950/50 py-1 z-50"
                                    >
                                        <Link
                                            v-if="showCustomerNav"
                                            :href="dashboardUrl"
                                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-800 sm:hidden"
                                        >
                                            {{ t('nav.dashboard') }}
                                        </Link>
                                        <Link
                                            :href="profileUrl"
                                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-800"
                                        >
                                            {{ t('nav.profile') }}
                                        </Link>
                                        <Link
                                            :href="notificationSettingsUrl"
                                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-800"
                                        >
                                            <i class="pi pi-cog mr-2 text-xs"></i>{{ t('notification_settings.title') }}
                                        </Link>
                                        <Link
                                            v-if="isAdmin"
                                            href="/admin"
                                            class="block px-4 py-2 text-sm text-indigo-600 dark:text-indigo-400 hover:bg-gray-100 dark:hover:bg-dark-800"
                                        >
                                            <i class="pi pi-shield mr-2 text-xs"></i>{{ t('nav.admin') }}
                                        </Link>
                                        <div class="sm:hidden border-t border-gray-100 dark:border-dark-700 my-1"></div>
                                        <div class="sm:hidden px-4 py-2" @click.stop><LanguageSwitcher /></div>
                                        <div class="border-t border-gray-100 dark:border-dark-700 my-1"></div>
                                        <button
                                            @click="logout"
                                            class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-800 cursor-pointer"
                                        >
                                            {{ t('nav.logout') }}
                                        </button>
                                    </div>
                                </Transition>
                            </div>

                            <!-- Click-outside backdrop -->
                            <div v-if="dropdownOpen" @click="dropdownOpen = false" class="fixed inset-0 z-40"></div>
                        </template>

                        <!-- Guest links -->
                        <template v-else>
                            <Link
                                href="/login"
                                class="text-sm text-gray-600 dark:text-dark-400 hover:text-gray-900 dark:hover:text-white transition-colors px-2"
                            >
                                {{ t('nav.login') }}
                            </Link>
                            <Link
                                href="/register"
                                class="text-sm px-3 sm:px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors whitespace-nowrap"
                            >
                                {{ t('nav.register') }}
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page content -->
        <main>
            <slot />
        </main>
    </div>
</template>
