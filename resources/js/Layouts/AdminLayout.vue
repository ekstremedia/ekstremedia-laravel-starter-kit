<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Toast from 'primevue/toast';
import DarkModeToggle from '@/Components/DarkModeToggle.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import { useFlashToast } from '@/composables/useFlashToast';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const user = computed(() => page.props.auth?.user);
const currentPath = computed(() => page.url);
useFlashToast();

const nav = [
    { label: 'Overview', href: '/admin', icon: 'pi-home', match: (p: string) => p === '/admin' },
    { label: 'Users', href: '/admin/users', icon: 'pi-users', match: (p: string) => p.startsWith('/admin/users') },
    { label: 'Roles', href: '/admin/roles', icon: 'pi-shield', match: (p: string) => p.startsWith('/admin/roles') },
    { label: 'Permissions', href: '/admin/permissions', icon: 'pi-key', match: (p: string) => p.startsWith('/admin/permissions') },
    { label: 'Activity Log', href: '/admin/activity', icon: 'pi-list', match: (p: string) => p.startsWith('/admin/activity') },
    { label: 'Mail Settings', href: '/admin/mail', icon: 'pi-envelope', match: (p: string) => p.startsWith('/admin/mail') },
    { label: 'Backups', href: '/admin/backups', icon: 'pi-cloud-upload', match: (p: string) => p.startsWith('/admin/backups') },
    { label: 'Server & System', href: '/admin/system', icon: 'pi-server', match: (p: string) => p.startsWith('/admin/system') || p.startsWith('/admin/health') },
    { label: 'Horizon', href: '/horizon', icon: 'pi-compass', external: true },
    { label: 'Pulse', href: '/pulse', icon: 'pi-chart-line', external: true },
    { label: 'Logs', href: '/log-viewer', icon: 'pi-file', external: true },
];

const mobileOpen = ref(false);

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Toast position="top-right" />
    <div class="min-h-screen bg-gray-50 dark:bg-dark-950 text-gray-900 dark:text-gray-100">
        <aside class="fixed inset-y-0 left-0 w-64 bg-white dark:bg-dark-900 border-r border-gray-200 dark:border-dark-800 transform transition-transform z-40"
               :class="mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">
            <div class="h-16 flex items-center px-6 border-b border-gray-200 dark:border-dark-800">
                <Link href="/dashboard" class="text-lg font-semibold text-indigo-600 dark:text-indigo-400">
                    Admin
                </Link>
            </div>
            <nav class="p-3 space-y-1">
                <template v-for="item in nav" :key="item.href">
                    <a v-if="item.external" :href="item.href" target="_blank"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-800">
                        <i :class="['pi', item.icon]"></i>
                        <span>{{ item.label }}</span>
                        <i class="pi pi-external-link text-xs ml-auto opacity-60"></i>
                    </a>
                    <Link v-else :href="item.href"
                          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors"
                          :class="item.match && item.match(currentPath)
                              ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400'
                              : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-800'">
                        <i :class="['pi', item.icon]"></i>
                        <span>{{ item.label }}</span>
                    </Link>
                </template>
            </nav>
        </aside>

        <div class="md:pl-64">
            <header class="h-16 bg-white dark:bg-dark-900 border-b border-gray-200 dark:border-dark-800 flex items-center justify-between px-6">
                <button @click="mobileOpen = !mobileOpen" class="md:hidden text-gray-700 dark:text-gray-300">
                    <i class="pi pi-bars text-lg"></i>
                </button>
                <div class="flex items-center gap-3 ml-auto">
                    <Link href="/dashboard" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                        <i class="pi pi-arrow-left mr-1"></i> Back to app
                    </Link>
                    <LanguageSwitcher />
                    <DarkModeToggle />
                    <span v-if="user" class="text-sm text-gray-700 dark:text-gray-300">{{ user.full_name }}</span>
                    <button @click="logout" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                        Logout
                    </button>
                </div>
            </header>
            <main class="p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
