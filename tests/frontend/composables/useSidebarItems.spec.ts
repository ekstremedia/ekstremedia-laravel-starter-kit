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
    it('shows only global items (no customer-scoped entries) when outside a customer context', () => {
        const w = renderWithProps({
            auth: { user: { roles: [] } },
            chat: { enabled: false },
            tenancy: { enabled: false },
            app_settings: { files_feature_enabled: true },
            customer: null,
            available_customers: [{ id: 1, slug: 'acme', name: 'Acme', files_feature_enabled: true }],
        });
        const ids = w.vm.visible.filter(isSidebarItem).map((e) => e.id);
        expect(ids).toContain('home');
        expect(ids).not.toContain('chat');
        expect(ids).not.toContain('my-dashboard');
        expect(ids).not.toContain('files');
        expect(ids).not.toContain('company-files');
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

    it('hides files when global flag is off even when inside a files-enabled customer', () => {
        const w = renderWithProps({
            auth: { user: { roles: [] } },
            chat: { enabled: false },
            tenancy: { enabled: true },
            app_settings: { files_feature_enabled: false },
            customer: { id: 1, slug: 'acme', name: 'Acme', files_feature_enabled: true, company_files_enabled: true },
            available_customers: [{ id: 1, slug: 'acme', name: 'Acme', files_feature_enabled: true }],
        });
        const ids = w.vm.visible.filter(isSidebarItem).map((e) => e.id);
        expect(ids).not.toContain('files');
        expect(ids).not.toContain('company-files');
    });

    it('shows Private files inside a customer scope when both global and per-customer flags are on', () => {
        const w = renderWithProps({
            auth: { user: { roles: [] } },
            chat: { enabled: false },
            tenancy: { enabled: true },
            app_settings: { files_feature_enabled: true },
            customer: { id: 1, slug: 'acme', name: 'Acme', files_feature_enabled: true, company_files_enabled: false },
            available_customers: [{ id: 1, slug: 'acme', name: 'Acme', files_feature_enabled: true }],
        });
        const files = w.vm.visible.filter(isSidebarItem).find((e) => e.id === 'files');
        expect(files?.href).toBe('/c/acme/files');
    });

    it('shows Shared files only inside the customer scope and only when the user has the permission', () => {
        const inScopeWithPerm = renderWithProps({
            auth: { user: { roles: [], permissions: ['view company files'] } },
            chat: { enabled: false },
            tenancy: { enabled: true },
            app_settings: { files_feature_enabled: true },
            customer: { id: 1, slug: 'acme', name: 'Acme', files_feature_enabled: false, company_files_enabled: true },
            available_customers: [{ id: 1, slug: 'acme', name: 'Acme', files_feature_enabled: false }],
        });
        const shared = inScopeWithPerm.vm.visible.filter(isSidebarItem).find((e) => e.id === 'company-files');
        expect(shared?.href).toBe('/c/acme/files/company');

        const outOfScope = renderWithProps({
            auth: { user: { roles: [], permissions: ['view company files'] } },
            chat: { enabled: false },
            tenancy: { enabled: true },
            app_settings: { files_feature_enabled: true },
            customer: null,
            available_customers: [{ id: 1, slug: 'acme', name: 'Acme', files_feature_enabled: false }],
        });
        expect(outOfScope.vm.visible.filter(isSidebarItem).map((e) => e.id)).not.toContain('company-files');

        const inScopeNoPerm = renderWithProps({
            auth: { user: { roles: [], permissions: [] } },
            chat: { enabled: false },
            tenancy: { enabled: true },
            app_settings: { files_feature_enabled: true },
            customer: { id: 1, slug: 'acme', name: 'Acme', files_feature_enabled: false, company_files_enabled: true },
            available_customers: [{ id: 1, slug: 'acme', name: 'Acme', files_feature_enabled: false }],
        });
        expect(inScopeNoPerm.vm.visible.filter(isSidebarItem).map((e) => e.id)).not.toContain('company-files');
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
