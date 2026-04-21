import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import ConversationList from '@/Components/Chat/ConversationList.vue';
import type { ChatConversation } from '@/composables/useChat';

function conversation(overrides: Partial<ChatConversation> = {}): ChatConversation {
    return {
        id: 1,
        title: null,
        is_group: false,
        participants: [
            { id: 1, first_name: 'Me', last_name: 'Myself', avatar_thumb_url: null },
            { id: 2, first_name: 'Ada', last_name: 'Lovelace', avatar_thumb_url: null },
        ],
        unread_count: 0,
        last_message_at: null,
        latest_message: null,
        ...overrides,
    } as ChatConversation;
}

describe('ConversationList', () => {
    it('shows the empty state when there are no conversations', () => {
        const wrapper = mount(ConversationList, {
            props: { conversations: [], activeId: null, currentUserId: 1 },
        });

        expect(wrapper.text()).toContain('chat.no_conversations');
    });

    it('derives a display name from the other participants', () => {
        const wrapper = mount(ConversationList, {
            props: {
                conversations: [conversation()],
                activeId: null,
                currentUserId: 1,
            },
        });

        expect(wrapper.text()).toContain('Ada Lovelace');
    });

    it('prefers the group title when set', () => {
        const wrapper = mount(ConversationList, {
            props: {
                conversations: [conversation({ title: 'Design Team', is_group: true })],
                activeId: null,
                currentUserId: 1,
            },
        });

        expect(wrapper.text()).toContain('Design Team');
    });

    it('emits select with the conversation when clicked', async () => {
        const c = conversation();
        const wrapper = mount(ConversationList, {
            props: { conversations: [c], activeId: null, currentUserId: 1 },
        });

        await wrapper.findAll('button').find((b) => b.text().includes('Ada'))!.trigger('click');

        expect(wrapper.emitted('select')?.[0]?.[0]).toEqual(c);
    });

    it('emits newChat when the compose button is clicked', async () => {
        const wrapper = mount(ConversationList, {
            props: { conversations: [], activeId: null, currentUserId: 1 },
        });

        await wrapper.get('button[aria-label="chat.new_conversation"]').trigger('click');

        expect(wrapper.emitted('newChat')).toBeTruthy();
    });

    it('renders the unread badge and caps large counts at "9+"', () => {
        const wrapper = mount(ConversationList, {
            props: {
                conversations: [conversation({ unread_count: 12 })],
                activeId: null,
                currentUserId: 1,
            },
        });

        expect(wrapper.text()).toContain('9+');
    });

    it('marks the active row with aria-current', () => {
        const wrapper = mount(ConversationList, {
            props: {
                conversations: [conversation({ id: 42 })],
                activeId: 42,
                currentUserId: 1,
            },
        });

        expect(wrapper.find('[aria-current="true"]').exists()).toBe(true);
    });
});
