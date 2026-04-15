<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const cards = [
    { title: 'Users', href: '/admin/users', icon: 'pi-users', desc: 'Manage accounts, roles, verification.' },
    { title: 'Roles', href: '/admin/roles', icon: 'pi-shield', desc: 'Define who can do what.' },
    { title: 'Permissions', href: '/admin/permissions', icon: 'pi-key', desc: 'Granular permissions registry.' },
    { title: 'Activity Log', href: '/admin/activity', icon: 'pi-list', desc: 'Who changed what, when.' },
    { title: 'Health', href: '/admin/health', icon: 'pi-heart', desc: 'Queue, Reverb, Redis pings.' },
    { title: 'Mail Settings', href: '/admin/mail', icon: 'pi-envelope', desc: 'SMTP + test send.' },
    { title: 'System Info', href: '/admin/system', icon: 'pi-server', desc: 'PHP, Laravel, drivers.' },
    { title: 'Horizon', href: '/horizon', icon: 'pi-compass', desc: 'Queue dashboard.', external: true },
    { title: 'Pulse', href: '/pulse', icon: 'pi-chart-line', desc: 'App metrics.', external: true },
];
</script>

<template>
    <Head title="Admin" />
    <h1 class="text-2xl font-semibold mb-6">Admin Overview</h1>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <template v-for="c in cards" :key="c.href">
            <a v-if="c.external" :href="c.href" target="_blank"
               class="block p-5 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 hover:border-indigo-400 dark:hover:border-indigo-500 transition-colors">
                <div class="flex items-center gap-3 mb-2"><i :class="['pi', c.icon, 'text-indigo-500']"></i><span class="font-medium">{{ c.title }}</span></div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ c.desc }}</p>
            </a>
            <Link v-else :href="c.href"
                  class="block p-5 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 hover:border-indigo-400 dark:hover:border-indigo-500 transition-colors">
                <div class="flex items-center gap-3 mb-2"><i :class="['pi', c.icon, 'text-indigo-500']"></i><span class="font-medium">{{ c.title }}</span></div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ c.desc }}</p>
            </Link>
        </template>
    </div>
</template>
