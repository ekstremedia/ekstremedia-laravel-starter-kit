import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

vi.stubGlobal(
    'fetch',
    vi.fn(() => Promise.resolve(new Response(JSON.stringify({ users: [] }), { status: 200 }))),
);

import NewConversationDialog from '@/Components/Chat/NewConversationDialog.vue';

// The dialog uses <Teleport to="body">, so its DOM lives under document.body,
// not the wrapper root. Every test attaches to the body and queries there.
describe('NewConversationDialog', () => {
    beforeEach(() => {
        (globalThis.fetch as unknown as ReturnType<typeof vi.fn>).mockClear();
    });

    afterEach(() => {
        // Clean up any teleported nodes the tests leave behind.
        document.body.querySelectorAll('[role="dialog"]').forEach((n) => n.remove());
    });

    it('renders nothing when hidden', () => {
        const wrapper = mount(NewConversationDialog, {
            props: { visible: false },
            attachTo: document.body,
        });

        expect(document.querySelector('[role="dialog"]')).toBeNull();
        wrapper.unmount();
    });

    it('renders the search input when visible', () => {
        const wrapper = mount(NewConversationDialog, {
            props: { visible: true },
            attachTo: document.body,
        });

        expect(document.querySelector('[role="dialog"] input')).not.toBeNull();
        wrapper.unmount();
    });

    it('emits update:visible(false) when the cancel action is triggered', async () => {
        const wrapper = mount(NewConversationDialog, {
            props: { visible: true },
            attachTo: document.body,
        });

        const cancelBtn = Array.from(document.querySelectorAll<HTMLButtonElement>('[role="dialog"] button')).find(
            (b) => b.textContent?.includes('common.cancel'),
        );
        cancelBtn?.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:visible')?.at(-1)).toEqual([false]);
        wrapper.unmount();
    });

    it('disables the create button until at least one user is selected', () => {
        const wrapper = mount(NewConversationDialog, {
            props: { visible: true },
            attachTo: document.body,
        });

        const createBtn = Array.from(document.querySelectorAll<HTMLButtonElement>('[role="dialog"] button')).find(
            (b) => b.textContent?.includes('chat.start_chat'),
        );
        expect(createBtn?.disabled).toBe(true);
        wrapper.unmount();
    });

    it('skips the network request when the query is shorter than 2 chars', async () => {
        const wrapper = mount(NewConversationDialog, {
            props: { visible: true },
            attachTo: document.body,
        });

        const input = document.querySelector<HTMLInputElement>('[role="dialog"] input');
        if (input) {
            input.value = 'a';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        await new Promise((r) => setTimeout(r, 350));

        expect(globalThis.fetch).not.toHaveBeenCalled();
        wrapper.unmount();
    });
});
