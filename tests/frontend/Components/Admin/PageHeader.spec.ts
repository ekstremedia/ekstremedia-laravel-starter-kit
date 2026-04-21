import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import PageHeader from '@/Components/Admin/PageHeader.vue';

describe('PageHeader', () => {
    it('renders the title as an h1', () => {
        const wrapper = mount(PageHeader, { props: { title: 'Users' } });

        expect(wrapper.get('h1').text()).toBe('Users');
    });

    it('renders the description paragraph when provided', () => {
        const wrapper = mount(PageHeader, {
            props: { title: 'Users', description: 'Manage accounts and roles.' },
        });

        expect(wrapper.text()).toContain('Manage accounts and roles.');
        expect(wrapper.find('p').exists()).toBe(true);
    });

    it('omits the description paragraph when not provided', () => {
        const wrapper = mount(PageHeader, { props: { title: 'Users' } });

        expect(wrapper.find('p').exists()).toBe(false);
    });

    it('renders content from the actions slot', () => {
        const wrapper = mount(PageHeader, {
            props: { title: 'Users' },
            slots: { actions: '<button data-testid="new">New user</button>' },
        });

        expect(wrapper.find('[data-testid="new"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('New user');
    });

    it('hides the actions container when the slot is empty', () => {
        const wrapper = mount(PageHeader, { props: { title: 'Users' } });
        // The actions wrapper carries `shrink-0`; when nothing is slotted the div should not render.
        expect(wrapper.find('.shrink-0').exists()).toBe(false);
    });
});
