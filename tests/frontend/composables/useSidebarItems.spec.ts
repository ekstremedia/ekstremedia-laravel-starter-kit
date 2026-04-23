import { describe, it, expect, vi, afterEach } from 'vitest';
import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';

const pageState = { value: {} as Record<string, unknown> };

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: pageState.value }),
    router: {},
    Link: { name: 'Link', template: '<a><slot /></a>' },
}));

import { useSidebarItems } from '@/composables/useSidebarItems';
import { isSidebarItem } from '@/types/sidebar';

function renderWithProps(props: Record<string, unknown>) {
    pageState.value = props;
    const Host = defineComponent({
        setup() {
            const { visible } = useSidebarItems();
            return { visible };
        },
        template: '<div />',
    });
    return mount(Host);
}

afterEach(() => {
    pageState.value = {};
});

describe('useSidebarItems', () => {
    it('shows home, dashboard as base items', () => {
        const w = renderWithProps({
            auth: { user: { roles: [] } },
            chat: { enabled: false },
            tenancy: { enabled: false },
            app_settings: { files_feature_enabled: false },
            customer: null,
            available_customers: [],
        });
        const ids = w.vm.visible.filter(isSidebarItem).map((e) => e.id);
        expect(ids).toContain('home');
        expect(ids).toContain('my-dashboard');
        expect(ids).not.toContain('chat');
        expect(ids).not.toContain('files');
    });

    it('shows chat only when enabled', () => {
        const w = renderWithProps({
            auth: { user: { roles: [] } },
            chat: { enabled: true },
            tenancy: { enabled: false },
            app_settings: { files_feature_enabled: false },
            customer: null,
            available_customers: [],
        });
        const ids = w.vm.visible.filter(isSidebarItem).map((e) => e.id);
        expect(ids).toContain('chat');
    });

    it('hides files when global flag is off even with a files-enabled customer', () => {
        const w = renderWithProps({
            auth: { user: { roles: [] } },
            chat: { enabled: false },
            tenancy: { enabled: false },
            app_settings: { files_feature_enabled: false },
            customer: null,
            available_customers: [{ id: 1, slug: 'a', name: 'A', files_feature_enabled: true }],
        });
        const ids = w.vm.visible.filter(isSidebarItem).map((e) => e.id);
        expect(ids).not.toContain('files');
    });

    it('shows files when global flag is on and a customer has files enabled — links to that customer from non-customer routes', () => {
        const w = renderWithProps({
            auth: { user: { roles: [] } },
            chat: { enabled: false },
            tenancy: { enabled: false },
            app_settings: { files_feature_enabled: true },
            customer: null,
            available_customers: [{ id: 1, slug: 'acme', name: 'Acme', files_feature_enabled: true }],
        });
        const files = w.vm.visible.filter(isSidebarItem).find((e) => e.id === 'files');
        expect(files?.href).toBe('/c/acme/files');
    });

    it('includes the platform admin group only for SuperAdmin users', () => {
        const noAdmin = renderWithProps({
            auth: { user: { roles: [], is_super_admin: false } },
            chat: { enabled: false },
            tenancy: { enabled: true },
            app_settings: { files_feature_enabled: false },
            customer: null,
            available_customers: [],
        });
        expect(noAdmin.vm.visible.filter(isSidebarItem).map((e) => e.id)).not.toContain('users');

        const admin = renderWithProps({
            auth: { user: { roles: [], is_super_admin: true } },
            chat: { enabled: false },
            tenancy: { enabled: true },
            app_settings: { files_feature_enabled: false },
            customer: null,
            available_customers: [],
        });
        const adminIds = admin.vm.visible.filter(isSidebarItem).map((e) => e.id);
        expect(adminIds).toContain('users');
        expect(adminIds).toContain('customers');
        expect(adminIds).toContain('settings');
    });

    it('shows a customer Members link for a customer-Admin when inside a customer', () => {
        const w = renderWithProps({
            auth: { user: { roles: ['Admin'], is_super_admin: false } },
            chat: { enabled: false },
            tenancy: { enabled: true },
            app_settings: { files_feature_enabled: false },
            customer: { id: 1, slug: 'acme', name: 'Acme' },
            available_customers: [{ id: 1, slug: 'acme', name: 'Acme' }],
        });
        const ids = w.vm.visible.filter(isSidebarItem).map((e) => e.id);
        expect(ids).toContain('members');
        // Customer-Admin without the SuperAdmin flag doesn't see the platform group.
        expect(ids).not.toContain('users');
    });
});
