<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { usePage, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Toast from 'primevue/toast';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import DarkModeToggle from '@/Components/DarkModeToggle.vue';
import { useFlashToast } from '@/composables/useFlashToast';
import type { PageProps } from '@/types';

const { t } = useI18n();
const page = usePage<PageProps>();
const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => user.value?.roles?.includes('Admin') ?? false);
const appName = import.meta.env.VITE_APP_NAME || t('app.name');

const dropdownOpen = ref(false);
const notificationsOpen = ref(false);
useFlashToast();

const unreadCount = computed(() => user.value?.unread_notifications_count ?? 0);
const isImpersonating = computed(() => user.value?.is_impersonating ?? false);

function logout() {
    router.post('/logout');
}

function leaveImpersonation() {
    router.post('/impersonate/leave');
}

function markAllRead() {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
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
        <!-- Impersonation banner -->
        <div v-if="isImpersonating" class="bg-amber-500/90 text-white px-4 py-2 text-sm flex items-center justify-between">
            <div><i class="pi pi-user-edit mr-2"></i>You are impersonating <strong>{{ user?.email }}</strong>.</div>
            <button @click="leaveImpersonation" class="px-3 py-1 rounded bg-white/20 hover:bg-white/30 text-sm">
                <i class="pi pi-sign-out mr-1"></i>Stop impersonating
            </button>
        </div>
        <!-- Navigation -->
        <nav class="border-b border-gray-200 dark:border-dark-800 bg-white dark:bg-dark-900 transition-colors">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <!-- Left: Logo + nav links -->
                    <div class="flex items-center gap-8">
                        <Link href="/" class="text-xl font-bold text-indigo-600 dark:text-indigo-400 transition-colors hover:text-indigo-500">
                            {{ appName }}
                        </Link>

                        <template v-if="user">
                            <div class="hidden sm:flex items-center gap-1">
                                <Link
                                    href="/dashboard"
                                    class="px-3 py-2 text-sm font-medium rounded-lg transition-colors"
                                    :class="$page.url === '/dashboard'
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
                                    Admin
                                </Link>
                            </div>
                        </template>
                    </div>

                    <!-- Right side -->
                    <div class="flex items-center gap-3">
                        <LanguageSwitcher />
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
                                            <span class="text-sm font-medium">Notifications</span>
                                            <button v-if="unreadCount > 0" @click="markAllRead" class="text-xs text-indigo-500 hover:underline">Mark all read</button>
                                        </div>
                                        <p v-if="unreadCount === 0" class="px-4 py-6 text-center text-sm text-gray-400">You're all caught up.</p>
                                        <p v-else class="px-4 py-6 text-center text-sm text-gray-500">
                                            {{ unreadCount }} unread {{ unreadCount === 1 ? 'notification' : 'notifications' }}
                                        </p>
                                    </div>
                                </Transition>
                                <div v-if="notificationsOpen" @click="notificationsOpen = false" class="fixed inset-0 z-40"></div>
                            </div>
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
                                            href="/dashboard"
                                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-800 sm:hidden"
                                        >
                                            {{ t('nav.dashboard') }}
                                        </Link>
                                        <Link
                                            href="/profile"
                                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-800"
                                        >
                                            {{ t('nav.profile') }}
                                        </Link>
                                        <Link
                                            v-if="isAdmin"
                                            href="/admin"
                                            class="block px-4 py-2 text-sm text-indigo-600 dark:text-indigo-400 hover:bg-gray-100 dark:hover:bg-dark-800"
                                        >
                                            <i class="pi pi-shield mr-2 text-xs"></i>Admin
                                        </Link>
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
                                class="text-sm text-gray-600 dark:text-dark-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                            >
                                {{ t('nav.login') }}
                            </Link>
                            <Link
                                href="/register"
                                class="text-sm px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors"
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
