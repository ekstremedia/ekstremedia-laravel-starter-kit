import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';

// Override the global vue-i18n mock to include `locale` (MessageInput formats
// attachment sizes with Intl.NumberFormat, which needs a locale ref).
vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string) => key,
        locale: { value: 'en-US' },
    }),
}));

import MessageInput from '@/Components/Chat/MessageInput.vue';

function fileWithSize(name: string, size: number): File {
    const f = new File([''], name, { type: 'text/plain' });
    Object.defineProperty(f, 'size', { value: size });
    return f;
}

describe('MessageInput', () => {
    it('disables the send button when textarea is empty and no files', () => {
        const wrapper = mount(MessageInput);
        const sendBtn = wrapper.get('button[aria-label="chat.send"]');

        expect((sendBtn.element as HTMLButtonElement).disabled).toBe(true);
    });

    it('enables the send button when the user types something', async () => {
        const wrapper = mount(MessageInput);
        await wrapper.get('textarea').setValue('hello');

        const sendBtn = wrapper.get('button[aria-label="chat.send"]');
        expect((sendBtn.element as HTMLButtonElement).disabled).toBe(false);
    });

    it('emits send with trimmed body when Enter is pressed without shift', async () => {
        const wrapper = mount(MessageInput);

        await wrapper.get('textarea').setValue('  hi there  ');
        await wrapper.get('textarea').trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('send')?.[0]).toEqual([{ body: 'hi there', files: [] }]);
    });

    it('inserts a newline instead of sending on Shift+Enter', async () => {
        const wrapper = mount(MessageInput);

        await wrapper.get('textarea').setValue('line 1');
        await wrapper.get('textarea').trigger('keydown', { key: 'Enter', shiftKey: true });

        expect(wrapper.emitted('send')).toBeFalsy();
    });

    it('emits a throttled typing event on input', async () => {
        const wrapper = mount(MessageInput);

        await wrapper.get('textarea').setValue('a');
        await wrapper.get('textarea').trigger('input');

        expect(wrapper.emitted('typing')).toBeTruthy();
    });

    it('does not emit send when submit is called with an empty body and no files', async () => {
        const wrapper = mount(MessageInput);

        await wrapper.get('button[aria-label="chat.send"]').trigger('click');

        expect(wrapper.emitted('send')).toBeFalsy();
    });

    it('clears state after a successful send', async () => {
        const wrapper = mount(MessageInput);

        await wrapper.get('textarea').setValue('hello');
        await wrapper.get('textarea').trigger('keydown', { key: 'Enter' });

        expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('');
    });

    it('opens the file picker button without crashing', async () => {
        const wrapper = mount(MessageInput);

        const attachBtn = wrapper.get('button[aria-label="chat.attach_files"]');
        await attachBtn.trigger('click');

        // No assertion beyond "doesn't throw" — JSDOM won't open a real picker.
        expect(attachBtn.exists()).toBe(true);
    });

    it('renders a pending attachment chip when a file is attached', async () => {
        const wrapper = mount(MessageInput);

        const input = wrapper.get('input[type="file"]').element as HTMLInputElement;
        const file = fileWithSize('note.txt', 128);

        Object.defineProperty(input, 'files', {
            configurable: true,
            value: [file],
        });
        await wrapper.get('input[type="file"]').trigger('change');

        expect(wrapper.text()).toContain('note.txt');
    });
});
