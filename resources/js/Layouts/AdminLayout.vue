<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted } from 'vue';
import Toast from 'primevue/toast';
import Tooltip from 'primevue/tooltip';
import DarkModeToggle from '@/Components/DarkModeToggle.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import ChatDropdown from '@/Components/Chat/ChatDropdown.vue';
import { useFlashToast } from '@/composables/useFlashToast';
import { useCustomer } from '@/composables/useCustomer';
import { useSettings } from '@/composables/useSettings';
import { useUnreadCounts } from '@/composables/useUnreadCounts';
import { useUserChannel } from '@/composables/useUserChannel';
import type { PageProps } from '@/types';

const vTooltip = Tooltip;

const { t } = useI18n();
const page = usePage<PageProps>();
const user = computed(() => page.props.auth?.user);
const currentPath = computed(() => page.url);
const { tenancyEnabled } = useCustomer();
const chatEnabled = computed(() => page.props.chat?.enabled ?? false);
useFlashToast();

const { settings, update } = useSettings();
const { messagesCount: unreadMessagesCount, incrementMessages, incrementNotifications } = useUnreadCounts();

// Server-pushed notifications keep admin navbar badges in sync.
// Chat messages only touch the message badge; everything else bumps the bell.
useUserChannel((n) => {
    const isChat = typeof n.type === 'string' && n.type.endsWith('NewChatMessageNotification');
    if (isChat) {
        incrementMessages(1);
        return;
    }
    incrementNotifications(1);
});

interface NavItem {
    label: string;
    href: string;
    icon: string;
    match?: (p: string) => boolean;
    external?: boolean;
    customerOnly?: boolean;
}

const navItems = computed<NavItem[]>(() => [
    { label: t('admin.nav.overview'), href: '/admin', icon: 'pi-home', match: (p: string) => p === '/admin' },
    { label: t('admin.nav.customers'), href: '/admin/customers', icon: 'pi-building', match: (p: string) => p.startsWith('/admin/customers'), customerOnly: true },
    { label: t('admin.nav.users'), href: '/admin/users', icon: 'pi-users', match: (p: string) => p.startsWith('/admin/users') },
    { label: t('admin.nav.roles'), href: '/admin/roles', icon: 'pi-shield', match: (p: string) => p.startsWith('/admin/roles') },
    { label: t('admin.nav.permissions'), href: '/admin/permissions', icon: 'pi-key', match: (p: string) => p.startsWith('/admin/permissions') },
    { label: t('admin.nav.activity_log'), href: '/admin/activity', icon: 'pi-list', match: (p: string) => p.startsWith('/admin/activity') },
    { label: t('admin.nav.mail_settings'), href: '/admin/mail', icon: 'pi-envelope', match: (p: string) => p.startsWith('/admin/mail') },
    { label: t('admin.nav.app_settings'), href: '/admin/settings', icon: 'pi-sliders-h', match: (p: string) => p === '/admin/settings' },
    { label: t('admin.nav.backups'), href: '/admin/backups', icon: 'pi-cloud-upload', match: (p: string) => p.startsWith('/admin/backups') },
    { label: t('admin.nav.system'), href: '/admin/system', icon: 'pi-server', match: (p: string) => p.startsWith('/admin/system') || p.startsWith('/admin/health') },
    { label: t('admin.nav.horizon'), href: '/horizon', icon: 'pi-compass', external: true },
    { label: t('admin.nav.pulse'), href: '/pulse', icon: 'pi-chart-line', external: true },
    { label: t('admin.nav.logs'), href: '/log-viewer', icon: 'pi-file', external: true },
]);

const nav = computed<NavItem[]>(() => navItems.value.filter((item) => !item.customerOnly || tenancyEnabled.value));

const mobileOpen = ref(false);
const userMenuOpen = ref(false);

// Collapsible sidebar — persisted per user via settings composable.
const sidebarCollapsed = ref<boolean>(Boolean(settings.value.admin_sidebar_collapsed ?? false));

onMounted(() => {
    sidebarCollapsed.value = Boolean(settings.value.admin_sidebar_collapsed ?? false);
});

watch(() => settings.value.admin_sidebar_collapsed, (v) => {
    sidebarCollapsed.value = Boolean(v);
});

function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    update({ admin_sidebar_collapsed: sidebarCollapsed.value });
}

function logout() {
    router.post('/logout');
}

// Close the mobile nav whenever the route changes
watch(currentPath, () => {
    mobileOpen.value = false;
    userMenuOpen.value = false;
});
</script>

<template>
    <Toast position="top-right" />
    <div class="min-h-screen bg-gray-50 dark:bg-dark-950 text-gray-900 dark:text-gray-100">
        <!-- Mobile backdrop -->
        <Transition enter-active-class="transition-opacity" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition-opacity" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="mobileOpen" class="md:hidden fixed inset-0 bg-black/50 z-30" @click="mobileOpen = false"></div>
        </Transition>

        <aside
            class="fixed inset-y-0 left-0 bg-white dark:bg-dark-900 border-r border-gray-200 dark:border-dark-800 transform transition-all duration-200 z-40"
            :class="[
                mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
                sidebarCollapsed ? 'w-16' : 'w-64 max-w-[80%]',
            ]"
        >
            <div class="h-16 flex items-center border-b border-gray-200 dark:border-dark-800"
                 :class="sidebarCollapsed ? 'justify-center' : 'justify-between px-4 sm:px-6'">
                <Link v-if="!sidebarCollapsed" href="/app" class="text-lg font-semibold text-indigo-600 dark:text-indigo-400">
                    {{ t('nav.admin') }}
                </Link>
                <Link v-else href="/app" class="text-indigo-600 dark:text-indigo-400" :title="t('nav.admin')">
                    <i class="pi pi-shield"></i>
                </Link>
                <button v-if="!sidebarCollapsed" @click="mobileOpen = false" class="md:hidden text-gray-500 dark:text-gray-400 p-1 cursor-pointer"
                        aria-label="Close navigation">
                    <i class="pi pi-times"></i>
                </button>
            </div>
            <nav class="p-3 space-y-1 overflow-y-auto max-h-[calc(100vh-4rem)]">
                <template v-for="item in nav" :key="item.href">
                    <a v-if="item.external" :href="item.href" target="_blank"
                       v-tooltip.right="sidebarCollapsed ? item.label : null"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-800"
                       :class="sidebarCollapsed ? 'justify-center' : ''">
                        <i :class="['pi', item.icon]"></i>
                        <span v-if="!sidebarCollapsed">{{ item.label }}</span>
                        <i v-if="!sidebarCollapsed" class="pi pi-external-link text-xs ml-auto opacity-60"></i>
                    </a>
                    <Link v-else :href="item.href"
                          v-tooltip.right="sidebarCollapsed ? item.label : null"
                          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors"
                          :class="[
                              item.match && item.match(currentPath)
                                  ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400'
                                  : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-800',
                              sidebarCollapsed ? 'justify-center' : '',
                          ]">
                        <i :class="['pi', item.icon]"></i>
                        <span v-if="!sidebarCollapsed">{{ item.label }}</span>
                    </Link>
                </template>
            </nav>

            <!-- Collapse toggle (desktop only) -->
            <button
                type="button"
                @click="toggleSidebar"
                class="hidden md:flex absolute bottom-3 left-0 right-0 mx-3 items-center justify-center p-2 rounded-lg text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-800 cursor-pointer border border-gray-200 dark:border-dark-700"
                :aria-label="sidebarCollapsed ? t('admin.nav.expand_sidebar') : t('admin.nav.collapse_sidebar')"
                v-tooltip.right="sidebarCollapsed ? t('admin.nav.expand_sidebar') : null"
            >
                <i :class="sidebarCollapsed ? 'pi pi-angle-double-right' : 'pi pi-angle-double-left'"></i>
                <span v-if="!sidebarCollapsed" class="ml-2">{{ t('admin.nav.collapse_sidebar') }}</span>
            </button>
        </aside>

        <div :class="sidebarCollapsed ? 'md:pl-16' : 'md:pl-64'" class="transition-all duration-200">
            <header class="h-16 bg-white dark:bg-dark-900 border-b border-gray-200 dark:border-dark-800 flex items-center justify-between gap-2 px-4 sm:px-6 sticky top-0 z-20">
                <button @click="mobileOpen = !mobileOpen"
                        class="md:hidden text-gray-700 dark:text-gray-300 p-2 -ml-2 cursor-pointer"
                        aria-label="Open navigation">
                    <i class="pi pi-bars text-lg"></i>
                </button>

                <!-- Desktop utility bar -->
                <div class="hidden md:flex items-center gap-2 ml-auto">
                    <Link href="/app" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white px-2 py-1 rounded-lg hover:bg-gray-100 dark:hover:bg-dark-800">
                        <i class="pi pi-arrow-left mr-1"></i> {{ t('nav.back_to_app') }}
                    </Link>
                    <LanguageSwitcher />
                    <DarkModeToggle />
                    <template v-if="user">
                        <NotificationBell />
                    </template>
                    <template v-if="user && chatEnabled">
                        <ChatDropdown :unread-count="unreadMessagesCount" :current-user-id="user.id" />
                    </template>
                    <span v-if="user" class="text-sm text-gray-700 dark:text-gray-300 truncate max-w-[12rem]">{{ user.full_name }}</span>
                    <button @click="logout" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white px-2 py-1 cursor-pointer">
                        {{ t('nav.logout') }}
                    </button>
                </div>

                <!-- Mobile: compact user button with dropdown -->
                <div class="md:hidden flex items-center gap-2 ml-auto">
                    <DarkModeToggle />
                    <template v-if="user">
                        <NotificationBell />
                    </template>
                    <template v-if="user && chatEnabled">
                        <ChatDropdown :unread-count="unreadMessagesCount" :current-user-id="user.id" />
                    </template>
                    <div class="relative">
                        <button @click="userMenuOpen = !userMenuOpen"
                                class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-dark-800 cursor-pointer"
                                aria-label="User menu">
                            <img v-if="user?.avatar_thumb_url" :src="user.avatar_thumb_url" :alt="user.full_name"
                                 class="w-7 h-7 rounded-full object-cover" />
                            <div v-else class="w-7 h-7 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[10px] font-semibold">
                                {{ ((user?.first_name?.[0] ?? '') + (user?.last_name?.[0] ?? '')).toUpperCase() }}
                            </div>
                            <i class="pi pi-chevron-down text-xs text-gray-400"></i>
                        </button>
                        <Transition enter-active-class="transition" enter-from-class="opacity-0 scale-95"
                                    enter-to-class="opacity-100 scale-100" leave-active-class="transition"
                                    leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                            <div v-if="userMenuOpen"
                                 class="absolute right-0 mt-2 w-56 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700 shadow-lg py-2 z-50">
                                <div class="px-4 py-2 border-b border-gray-100 dark:border-dark-800">
                                    <p class="text-sm font-medium truncate">{{ user?.full_name }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ user?.email }}</p>
                                </div>
                                <Link href="/app" class="block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-dark-800">
                                    <i class="pi pi-arrow-left mr-2 text-xs"></i>{{ t('nav.back_to_app') }}
                                </Link>
                                <Link href="/profile" class="block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-dark-800">
                                    <i class="pi pi-user mr-2 text-xs"></i>{{ t('nav.profile') }}
                                </Link>
                                <div class="border-t border-gray-100 dark:border-dark-800 my-1"></div>
                                <div class="px-4 py-2"><LanguageSwitcher /></div>
                                <div class="border-t border-gray-100 dark:border-dark-800 my-1"></div>
                                <button @click="logout"
                                        class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-dark-800 cursor-pointer">
                                    <i class="pi pi-sign-out mr-2 text-xs"></i>{{ t('nav.logout') }}
                                </button>
                            </div>
                        </Transition>
                        <div v-if="userMenuOpen" @click="userMenuOpen = false" class="fixed inset-0 z-40"></div>
                    </div>
                </div>
            </header>
            <main class="p-4 sm:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
