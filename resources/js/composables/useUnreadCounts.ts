import { effectScope, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const messagesCount = ref<number>(0);
const notificationsCount = ref<number>(0);
let initialized = false;

function clamp(n: number): number {
    return Math.max(0, Math.floor(n));
}

export function useUnreadCounts() {
    const page = usePage<PageProps>();

    if (!initialized) {
        initialized = true;
        messagesCount.value = clamp(page.props.auth?.user?.unread_messages_count ?? 0);
        notificationsCount.value = clamp(page.props.auth?.user?.unread_notifications_count ?? 0);

        // Create the watcher in a detached effect scope so it outlives the
        // component that happened to trigger the first `useUnreadCounts()`
        // call. Without this, the shared state stops syncing after that
        // component unmounts.
        const scope = effectScope(true);
        scope.run(() => {
            watch(
                () => [
                    page.props.auth?.user?.unread_messages_count,
                    page.props.auth?.user?.unread_notifications_count,
                ] as const,
                ([m, n]) => {
                    // Non-numbers (undefined on logout, null) should reset to 0
                    // rather than silently keeping stale counts around.
                    messagesCount.value = clamp(typeof m === 'number' ? m : 0);
                    notificationsCount.value = clamp(typeof n === 'number' ? n : 0);
                },
            );
        });
    }

    return {
        messagesCount,
        notificationsCount,
        decrementMessages(by = 1) {
            messagesCount.value = clamp(messagesCount.value - by);
        },
        incrementMessages(by = 1) {
            messagesCount.value = clamp(messagesCount.value + by);
        },
        setMessages(value: number) {
            messagesCount.value = clamp(value);
        },
        decrementNotifications(by = 1) {
            notificationsCount.value = clamp(notificationsCount.value - by);
        },
        incrementNotifications(by = 1) {
            notificationsCount.value = clamp(notificationsCount.value + by);
        },
        setNotifications(value: number) {
            notificationsCount.value = clamp(value);
        },
    };
}
