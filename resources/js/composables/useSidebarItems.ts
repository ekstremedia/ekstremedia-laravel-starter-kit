/*
 * Single source of truth for the left rail / sidebar nav.
 *
 * To add, remove, or reorder an item: edit the `entries` array below. Every
 * presentational concern (collapse state, tooltip, active-indicator, render
 * loop) lives in Rail.vue and does not need to change.
 */
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { PageProps } from '@/types';
import type { SidebarEntry } from '@/types/sidebar';

export function useSidebarItems() {
    const { t } = useI18n();
    const page = usePage<PageProps>();

    const user = computed(() => page.props.auth?.user);
    const isSuperAdmin = computed(() => user.value?.is_super_admin === true);
    const isCustomerAdmin = computed(() => (user.value?.roles ?? []).includes('Admin'));
    const tenancyEnabled = computed(() => page.props.tenancy?.enabled ?? false);
    const chatEnabled = computed(() => page.props.chat?.enabled ?? false);
    const globalFilesEnabled = computed(() => page.props.app_settings?.files_feature_enabled ?? false);

    const customer = computed(() => page.props.customer);
    const customers = computed(() => page.props.available_customers ?? []);
    const customerSlug = computed(() => customer.value?.slug ?? null);

    // Files link target: current customer if set, else the first available
    // customer that has files enabled. Keeps "Filer" discoverable on non-
    // customer routes (e.g. /home, /chat).
    const filesTarget = computed(() => {
        if (customer.value?.files_feature_enabled) return customer.value;
        return customers.value.find((c) => c.files_feature_enabled) ?? null;
    });

    const items = computed<SidebarEntry[]>(() => {
        const dashboardHref = customerSlug.value ? `/c/${customerSlug.value}/dashboard` : '/app';
        const filesHref = filesTarget.value ? `/c/${filesTarget.value.slug}/files` : '/app';

        const entries: SidebarEntry[] = [
            { id: 'home', href: '/home', label: t('rail.home'), icon: 'user', kb: 'H', match: (p) => p === '/home' || p === '/' },
            { id: 'my-dashboard', href: dashboardHref, label: t('rail.dashboard'), icon: 'home', match: (p) => p.startsWith('/c/') && p.includes('/dashboard') },
            { id: 'files', href: filesHref, label: t('rail.files'), icon: 'disk', match: (p) => p.startsWith('/c/') && p.includes('/files'), hideWhen: () => !globalFilesEnabled.value || !filesTarget.value },
            { id: 'chat', href: '/chat', label: t('rail.chat'), icon: 'mail', match: (p) => p.startsWith('/chat'), hideWhen: () => !chatEnabled.value },
        ];

        // Customer-admin: only meaningful inside a customer context. Shows the
        // Members link for the active customer.
        if (isCustomerAdmin.value && customerSlug.value) {
            entries.push(
                { separator: true, key: 'c1' },
                { id: 'members', href: `/c/${customerSlug.value}/members`, label: t('rail.members'), icon: 'users', match: (p) => p.startsWith(`/c/${customerSlug.value}/members`) },
            );
        }

        if (isSuperAdmin.value) {
            entries.push(
                { separator: true, key: 's1' },
                { id: 'dashboard', href: '/admin', label: t('rail.admin_overview'), icon: 'home', kb: 'D', match: (p) => p === '/admin' },
                { id: 'users', href: '/admin/users', label: t('rail.users'), icon: 'users', kb: 'U', match: (p) => p.startsWith('/admin/users') },
                { id: 'customers', href: '/admin/customers', label: t('rail.customers'), icon: 'customer', match: (p) => p.startsWith('/admin/customers'), hideWhen: () => !tenancyEnabled.value },
                { id: 'roles', href: '/admin/roles', label: t('rail.roles'), icon: 'role', match: (p) => p.startsWith('/admin/roles') },
                { id: 'perms', href: '/admin/permissions', label: t('rail.permissions'), icon: 'key', match: (p) => p.startsWith('/admin/permissions') },
                { separator: true, key: 's2' },
                { id: 'settings', href: '/admin/settings', label: t('rail.app_settings'), icon: 'cog', kb: 'A', match: (p) => p === '/admin/settings' },
                { id: 'mail', href: '/admin/mail', label: t('rail.mail'), icon: 'mail', match: (p) => p.startsWith('/admin/mail') },
                { id: 'storage', href: '/admin/storage', label: t('rail.storage'), icon: 'disk', match: (p) => p.startsWith('/admin/storage') },
                { id: 'backups', href: '/admin/backups', label: t('rail.backups'), icon: 'shield', match: (p) => p.startsWith('/admin/backups') },
                { id: 'server', href: '/admin/system', label: t('rail.server'), icon: 'server', match: (p) => p.startsWith('/admin/system') || p.startsWith('/admin/health') },
                { separator: true, key: 's3' },
                { id: 'logs', href: '/admin/monitoring', label: t('rail.logs'), icon: 'log', match: (p) => p.startsWith('/admin/monitoring') || p.startsWith('/admin/activity') },
            );
        }

        return entries;
    });

    const visible = computed<SidebarEntry[]>(() =>
        items.value.filter((entry) => ('separator' in entry) ? true : !entry.hideWhen?.()),
    );

    return { items, visible };
}
