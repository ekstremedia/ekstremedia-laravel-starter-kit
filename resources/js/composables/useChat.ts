import { ref, onUnmounted } from 'vue';

export interface ChatUser {
    id: number;
    first_name: string;
    last_name: string;
    avatar_thumb_url?: string | null;
}

export interface ChatMessage {
    id: number;
    conversation_id: number;
    user_id: number;
    user: ChatUser;
    body: string;
    type: string;
    created_at: string;
}

export interface ChatConversation {
    id: number;
    title: string | null;
    is_group: boolean;
    participants: ChatUser[];
    latest_message: {
        id: number;
        body: string;
        user_id: number;
        user: { id: number; first_name: string };
        created_at: string;
    } | null;
    unread_count: number;
    last_message_at: string | null;
}

/**
 * Composable for managing Echo subscriptions on a chat conversation channel.
 * Handles real-time incoming messages and typing indicators.
 */
export function useChat() {
    const messages = ref<ChatMessage[]>([]);
    const typingUser = ref<string | null>(null);
    let typingTimeout: ReturnType<typeof setTimeout> | null = null;
    let currentChannelName: string | null = null;

    function joinConversation(conversationId: number, onMessage?: (msg: ChatMessage) => void) {
        leaveConversation();

        currentChannelName = `chat.conversation.${conversationId}`;

        window.Echo?.private(currentChannelName)
            .listen('.message.sent', (e: { message: ChatMessage; sender: ChatUser }) => {
                const msg: ChatMessage = {
                    ...e.message,
                    user: e.sender,
                };
                messages.value.push(msg);
                onMessage?.(msg);
            })
            .listenForWhisper('typing', (e: { name: string }) => {
                typingUser.value = e.name;
                if (typingTimeout) clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => {
                    typingUser.value = null;
                }, 3000);
            });
    }

    function leaveConversation() {
        if (currentChannelName) {
            window.Echo?.leave(currentChannelName);
            currentChannelName = null;
        }
        messages.value = [];
        typingUser.value = null;
        if (typingTimeout) {
            clearTimeout(typingTimeout);
            typingTimeout = null;
        }
    }

    function whisperTyping(conversationId: number, name: string) {
        const channelName = `chat.conversation.${conversationId}`;
        window.Echo?.private(channelName).whisper('typing', { name });
    }

    onUnmounted(() => {
        leaveConversation();
    });

    return {
        messages,
        typingUser,
        joinConversation,
        leaveConversation,
        whisperTyping,
    };
}
