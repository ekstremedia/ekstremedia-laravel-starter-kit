import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';

const pageProps: { props: Record<string, unknown> } = { props: {} };

vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual<any>('@inertiajs/vue3');
    return {
        ...actual,
        usePage: () => pageProps,
    };
});

import CustomerSwitcher from '@/Components/Command/CustomerSwitcher.vue';

function setPage(
    customers: Array<{ id: number; slug: string; name: string }>,
    current: { id: number; slug: string; name: string } | null = null,
    tenancyEnabled = true,
) {
    pageProps.props = {
        tenancy: { enabled: tenancyEnabled },
        available_customers: customers,
        customer: current,
    };
}

describe('CustomerSwitcher', () => {
    beforeEach(() => {
        pageProps.props = {};
    });

    it('renders nothing when tenancy is disabled', () => {
        setPage([{ id: 1, slug: 'a', name: 'A' }], null, false);
        const wrapper = mount(CustomerSwitcher);

        expect(wrapper.find('button').exists()).toBe(false);
    });

    it('renders nothing when the user has no memberships', () => {
        setPage([], null, true);
        const wrapper = mount(CustomerSwitcher);

        expect(wrapper.find('button').exists()).toBe(false);
    });

    it('renders a real link for a single customer even when the user is already inside it', () => {
        // A disabled "you're already here" badge was confusing — clicks on
        // the navbar chip now always navigate somewhere.
        setPage([{ id: 1, slug: 'acme', name: 'Acme' }], { id: 1, slug: 'acme', name: 'Acme' });
        const wrapper = mount(CustomerSwitcher);

        expect(wrapper.find('button').exists()).toBe(false);
        const link = wrapper.get('a');
        expect(link.attributes('href')).toBe('/c/acme/dashboard');
        expect(link.text()).toContain('Acme');
    });

    it('renders a link to the sole membership when no customer is active yet', () => {
        setPage([{ id: 1, slug: 'acme', name: 'Acme' }], null);
        const wrapper = mount(CustomerSwitcher);

        // A link — not a disabled "Pick a customer" button — so the welcome
        // page actually gets the user somewhere.
        expect(wrapper.find('button').exists()).toBe(false);
        const link = wrapper.get('a');
        expect(link.attributes('href')).toBe('/c/acme/dashboard');
        expect(link.text()).toContain('Acme');
    });

    it('opens the dropdown when there are multiple customers', async () => {
        setPage(
            [
                { id: 1, slug: 'acme', name: 'Acme' },
                { id: 2, slug: 'widgets', name: 'Widgets' },
            ],
            { id: 1, slug: 'acme', name: 'Acme' },
        );

        const wrapper = mount(CustomerSwitcher);
        await wrapper.get('button').trigger('click');

        expect(wrapper.text()).toContain('Widgets');
    });

    it('links each item to the customer dashboard', async () => {
        setPage(
            [
                { id: 1, slug: 'acme', name: 'Acme' },
                { id: 2, slug: 'widgets', name: 'Widgets' },
            ],
            { id: 1, slug: 'acme', name: 'Acme' },
        );

        const wrapper = mount(CustomerSwitcher);
        await wrapper.get('button').trigger('click');

        const links = wrapper.findAll('a').map((a) => a.attributes('href'));
        expect(links).toContain('/c/acme/dashboard');
        expect(links).toContain('/c/widgets/dashboard');
    });
});
