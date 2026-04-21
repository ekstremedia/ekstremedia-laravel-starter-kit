import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import MessageThread from '@/Components/Chat/MessageThread.vue';
import type { ChatMessage } from '@/composables/useChat';

function msg(overrides: Partial<ChatMessage> = {}): ChatMessage {
    return {
        id: 1,
        user_id: 1,
        body: 'hello',
        created_at: new Date('2026-04-21T10:00:00Z').toISOString(),
        user: {
            id: 1,
            first_name: 'Me',
            last_name: 'Myself',
            avatar_thumb_url: null,
        },
        attachments: [],
        ...overrides,
    } as ChatMessage;
}

describe('MessageThread', () => {
    it('renders an empty scroller when there are no messages', () => {
        const wrapper = mount(MessageThread, {
            props: {
                messages: [],
                currentUserId: 1,
                hasMore: false,
                loading: false,
                typingUser: null,
            },
        });

        expect(wrapper.findAll('[class*="message"]')).toHaveLength(0);
        // Scroll container always renders so layout holds height.
        expect(wrapper.find('.overflow-y-auto').exists()).toBe(true);
    });

    it('renders each message body once', () => {
        const wrapper = mount(MessageThread, {
            props: {
                messages: [msg({ id: 1, body: 'first' }), msg({ id: 2, body: 'second' })],
                currentUserId: 1,
                hasMore: false,
                loading: false,
                typingUser: null,
            },
        });

        expect(wrapper.text()).toContain('first');
        expect(wrapper.text()).toContain('second');
    });

    it('shows the typing indicator when a user name is provided', () => {
        const wrapper = mount(MessageThread, {
            props: {
                messages: [msg()],
                currentUserId: 1,
                hasMore: false,
                loading: false,
                typingUser: 'Alice',
            },
        });

        expect(wrapper.text()).toContain('Alice');
    });

    it('does not render the typing indicator when no one is typing', () => {
        const wrapper = mount(MessageThread, {
            props: {
                messages: [msg()],
                currentUserId: 1,
                hasMore: false,
                loading: false,
                typingUser: null,
            },
        });

        expect(wrapper.text()).not.toContain('is typing');
    });

    it('emits loadMore when the user scrolls near the top with hasMore=true', async () => {
        const wrapper = mount(MessageThread, {
            props: {
                messages: [msg()],
                currentUserId: 1,
                hasMore: true,
                loading: false,
                typingUser: null,
            },
            attachTo: document.body,
        });

        const scroller = wrapper.get('.overflow-y-auto');
        Object.defineProperty(scroller.element, 'scrollTop', { configurable: true, value: 10 });
        Object.defineProperty(scroller.element, 'scrollHeight', { configurable: true, value: 1000 });
        Object.defineProperty(scroller.element, 'clientHeight', { configurable: true, value: 400 });

        await scroller.trigger('scroll');

        expect(wrapper.emitted('loadMore')).toBeTruthy();
        wrapper.unmount();
    });
});
