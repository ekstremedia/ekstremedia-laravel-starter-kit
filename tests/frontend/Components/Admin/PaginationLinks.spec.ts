import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import PaginationLinks from '@/Components/Admin/PaginationLinks.vue';

type Link = { url: string | null; label: string; active: boolean };

function withPages(pages: number): Link[] {
    // Matches the Laravel paginator shape: [prev, ...numbers, next]
    return [
        { url: null, label: '&laquo; Previous', active: false },
        ...Array.from({ length: pages }, (_, i) => ({
            url: `/admin/users?page=${i + 1}`,
            label: String(i + 1),
            active: i === 0,
        })),
        { url: '/admin/users?page=2', label: 'Next &raquo;', active: false },
    ];
}

describe('PaginationLinks', () => {
    it('does not render when there are no links', () => {
        const wrapper = mount(PaginationLinks, { props: { links: null } });
        expect(wrapper.find('nav').exists()).toBe(false);
    });

    it('does not render when the paginator is a single page (3 links or fewer)', () => {
        // Laravel paginator for a single page returns exactly 3 links: prev, 1, next
        const wrapper = mount(PaginationLinks, { props: { links: withPages(1) } });
        expect(wrapper.find('nav').exists()).toBe(false);
    });

    it('renders the nav once there is more than one page', () => {
        const wrapper = mount(PaginationLinks, { props: { links: withPages(3) } });
        expect(wrapper.find('nav').exists()).toBe(true);
    });

    it('renders anchor elements for links with a url', () => {
        const wrapper = mount(PaginationLinks, { props: { links: withPages(3) } });
        // Inertia Link renders an <a>. Excludes the prev link (null url) which becomes a <span>.
        expect(wrapper.findAll('a').length).toBeGreaterThan(0);
    });

    it('renders a disabled span for links with a null url', () => {
        const wrapper = mount(PaginationLinks, { props: { links: withPages(3) } });
        const spans = wrapper.findAll('span');
        expect(spans.length).toBeGreaterThanOrEqual(1);
        // The first link has url=null ("Previous") and should render as a span.
        expect(spans[0].text()).toContain('Previous');
    });

    it('highlights the active page link', () => {
        const wrapper = mount(PaginationLinks, { props: { links: withPages(3) } });
        const activeLink = wrapper.findAll('a').find((a) => a.text().trim() === '1');
        expect(activeLink?.classes().join(' ')).toContain('bg-gray-900');
    });
});
