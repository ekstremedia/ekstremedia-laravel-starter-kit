<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

// `computed` so labels re-evaluate when the user switches locale; a plain
// `const cards = [...]` captures translations once during setup.
const cards = computed(() => [
    { title: t('admin.overview.users'), href: '/admin/users', icon: 'pi-users', desc: t('admin.overview.users_desc') },
    { title: t('admin.overview.roles'), href: '/admin/roles', icon: 'pi-shield', desc: t('admin.overview.roles_desc') },
    { title: t('admin.overview.permissions'), href: '/admin/permissions', icon: 'pi-key', desc: t('admin.overview.permissions_desc') },
    { title: t('admin.overview.activity'), href: '/admin/activity', icon: 'pi-list', desc: t('admin.overview.activity_desc') },
    { title: t('admin.overview.health'), href: '/admin/health', icon: 'pi-heart', desc: t('admin.overview.health_desc') },
    { title: t('admin.overview.mail'), href: '/admin/mail', icon: 'pi-envelope', desc: t('admin.overview.mail_desc') },
    { title: t('admin.overview.backups'), href: '/admin/backups', icon: 'pi-cloud-upload', desc: t('admin.overview.backups_desc') },
    { title: t('admin.overview.system'), href: '/admin/system', icon: 'pi-server', desc: t('admin.overview.system_desc') },
    { title: t('admin.overview.horizon'), href: '/horizon', icon: 'pi-compass', desc: t('admin.overview.horizon_desc'), external: true },
    { title: t('admin.overview.pulse'), href: '/pulse', icon: 'pi-chart-line', desc: t('admin.overview.pulse_desc'), external: true },
    { title: t('admin.overview.logs'), href: '/log-viewer', icon: 'pi-file', desc: t('admin.overview.logs_desc'), external: true },
]);
</script>

<template>
    <Head title="Admin" />
    <h1 class="text-2xl font-semibold mb-6">{{ t('admin.overview.title') }}</h1>
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
