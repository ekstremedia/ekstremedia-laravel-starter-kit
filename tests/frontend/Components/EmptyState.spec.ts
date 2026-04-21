import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import EmptyState from '@/Components/EmptyState.vue';

describe('EmptyState', () => {
    it('renders the title and description', () => {
        const wrapper = mount(EmptyState, {
            props: { title: 'Nothing yet', description: 'You will see items here.' },
        });

        expect(wrapper.text()).toContain('Nothing yet');
        expect(wrapper.text()).toContain('You will see items here.');
    });

    it('renders the icon when provided', () => {
        const wrapper = mount(EmptyState, { props: { title: 't', icon: 'pi-inbox' } });

        expect(wrapper.find('.pi.pi-inbox').exists()).toBe(true);
    });

    it('omits the icon wrapper when no icon is set', () => {
        const wrapper = mount(EmptyState, { props: { title: 't' } });

        expect(wrapper.find('.pi').exists()).toBe(false);
    });

    it('renders the action slot', () => {
        const wrapper = mount(EmptyState, {
            props: { title: 't' },
            slots: { action: '<button data-testid="cta">Do it</button>' },
        });

        expect(wrapper.find('[data-testid="cta"]').exists()).toBe(true);
    });
});
