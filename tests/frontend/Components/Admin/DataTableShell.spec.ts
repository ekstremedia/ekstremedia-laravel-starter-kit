import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import DataTableShell from '@/Components/Admin/DataTableShell.vue';

describe('DataTableShell', () => {
    it('renders the count and count label in the header', () => {
        const wrapper = mount(DataTableShell, {
            props: { count: 12, countLabel: 'users' },
        });

        expect(wrapper.text()).toContain('12');
        expect(wrapper.text()).toContain('users');
    });

    it('omits the count block when count is not a number', () => {
        const wrapper = mount(DataTableShell, {
            props: { searchPlaceholder: 'Search…' },
        });

        expect(wrapper.text()).not.toMatch(/\b0\b/);
    });

    it('renders the search input when not hidden', () => {
        const wrapper = mount(DataTableShell, {
            props: { searchPlaceholder: 'Find user', searchValue: '' },
        });

        const input = wrapper.find('input');
        expect(input.exists()).toBe(true);
        expect(input.attributes('placeholder')).toBe('Find user');
    });

    it('omits the search input when hide-search is set', () => {
        const wrapper = mount(DataTableShell, {
            props: { hideSearch: true, count: 3, countLabel: 'rows' },
        });

        expect(wrapper.find('input').exists()).toBe(false);
    });

    it('reflects searchValue as the input value', () => {
        const wrapper = mount(DataTableShell, {
            props: { searchValue: 'alice' },
        });

        expect((wrapper.get('input').element as HTMLInputElement).value).toBe('alice');
    });

    it('emits update:searchValue on input', async () => {
        const wrapper = mount(DataTableShell, { props: { searchValue: '' } });

        await wrapper.get('input').setValue('bob');

        expect(wrapper.emitted('update:searchValue')?.at(-1)).toEqual(['bob']);
    });

    it('emits search-submit when Enter is pressed', async () => {
        const wrapper = mount(DataTableShell, { props: { searchValue: 'x' } });

        await wrapper.get('input').trigger('keydown.enter');

        expect(wrapper.emitted('search-submit')).toBeTruthy();
    });

    it('renders default slot content inside the card', () => {
        const wrapper = mount(DataTableShell, {
            props: { hideSearch: true },
            slots: { default: '<div data-testid="table">table body</div>' },
        });

        expect(wrapper.find('[data-testid="table"]').exists()).toBe(true);
    });

    it('renders the filters slot in the header row', () => {
        const wrapper = mount(DataTableShell, {
            props: { hideSearch: true, count: 1 },
            slots: { filters: '<button data-testid="filter">Filter</button>' },
        });

        expect(wrapper.find('[data-testid="filter"]').exists()).toBe(true);
    });

    it('collapses the header when nothing is shown', () => {
        const wrapper = mount(DataTableShell, { props: { hideSearch: true } });

        // With no count, no filters slot, no leading slot, and search hidden, the header bar should not render.
        expect(wrapper.find('.border-b').exists()).toBe(false);
    });
});
