import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import Breadcrumbs from '@/Components/Admin/Breadcrumbs.vue';

describe('Breadcrumbs', () => {
    it('renders nothing when no items are provided', () => {
        const wrapper = mount(Breadcrumbs, { props: { items: [] } });

        expect(wrapper.find('nav').exists()).toBe(false);
    });

    it('renders one <li> per crumb plus separators between them', () => {
        const wrapper = mount(Breadcrumbs, {
            props: {
                items: [
                    { label: 'Admin', href: '/admin' },
                    { label: 'Users', href: '/admin/users' },
                    { label: 'Alice' },
                ],
            },
        });

        const liCount = wrapper.findAll('li').length;
        // 3 crumbs + 2 separators = 5
        expect(liCount).toBe(5);
    });

    it('renders intermediate crumbs as links and the last crumb as text', () => {
        const wrapper = mount(Breadcrumbs, {
            props: {
                items: [
                    { label: 'Admin', href: '/admin' },
                    { label: 'Users' },
                ],
            },
        });

        const links = wrapper.findAll('a');
        expect(links).toHaveLength(1);
        expect(links[0].attributes('href')).toBe('/admin');
        expect(wrapper.text()).toContain('Users');
    });

    it('exposes a localized aria-label on the nav', () => {
        const wrapper = mount(Breadcrumbs, {
            props: { items: [{ label: 'Admin', href: '/admin' }, { label: 'Users' }] },
        });

        // The setup.ts i18n mock returns the key — so the aria-label collapses
        // to the key string, proving t(...) was used instead of a literal.
        expect(wrapper.get('nav').attributes('aria-label')).toBe('common.breadcrumb');
    });
});
