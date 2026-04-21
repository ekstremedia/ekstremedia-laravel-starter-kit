import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { ref } from 'vue';

const messagesCount = ref(0);
const decrementMessages = vi.fn();
const setMessages = vi.fn();
vi.mock('@/composables/useUnreadCounts', () => ({
    useUnreadCounts: () => ({ messagesCount, decrementMessages, setMessages }),
}));

vi.stubGlobal(
    'fetch',
    vi.fn(() => Promise.resolve(new Response(JSON.stringify({ conversations: [] }), { status: 200 }))),
);

import ChatDropdown from '@/Components/Chat/ChatDropdown.vue';

describe('ChatDropdown', () => {
    beforeEach(() => {
        messagesCount.value = 0;
        (globalThis.fetch as unknown as ReturnType<typeof vi.fn>).mockClear();
    });

    it('renders the bell button with the chat title as an accessible label', () => {
        const wrapper = mount(ChatDropdown, { props: { unreadCount: 0, currentUserId: 1 } });

        expect(wrapper.get('button').attributes('aria-label')).toBe('chat.title');
    });

    it('renders no badge when there are no unread messages', () => {
        const wrapper = mount(ChatDropdown, { props: { unreadCount: 0, currentUserId: 1 } });

        expect(wrapper.find('button > span').exists()).toBe(false);
    });

    it('shows the unread count as a badge', () => {
        const wrapper = mount(ChatDropdown, { props: { unreadCount: 4, currentUserId: 1 } });

        expect(wrapper.get('button > span').text()).toBe('4');
    });

    it('caps the unread badge at "99+"', () => {
        const wrapper = mount(ChatDropdown, { props: { unreadCount: 150, currentUserId: 1 } });

        expect(wrapper.get('button > span').text()).toBe('99+');
    });

    it('fetches conversations after opening', async () => {
        const wrapper = mount(ChatDropdown, { props: { unreadCount: 0, currentUserId: 1 } });

        await wrapper.get('button').trigger('click');
        // fetch runs synchronously inside the open watcher, before any microtask.
        expect(globalThis.fetch).toHaveBeenCalledWith(expect.stringContaining('/chat/conversations-list'), expect.any(Object));
    });

    it('renders the filter tabs when open', async () => {
        const wrapper = mount(ChatDropdown, { props: { unreadCount: 0, currentUserId: 1 } });

        await wrapper.get('button').trigger('click');

        expect(wrapper.text()).toContain('chat.filter_all');
        expect(wrapper.text()).toContain('chat.filter_unread');
        expect(wrapper.text()).toContain('chat.filter_groups');
    });
});
