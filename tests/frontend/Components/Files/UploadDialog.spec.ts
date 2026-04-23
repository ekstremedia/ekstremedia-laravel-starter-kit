import { mount } from '@vue/test-utils';
import { describe, it, expect, afterEach } from 'vitest';
import UploadDialog from '@/Components/Files/UploadDialog.vue';

// UploadDialog uses <Teleport to="body">, so look for its DOM in document.body
// rather than inside the wrapper root.
describe('UploadDialog', () => {
    afterEach(() => {
        document.body.querySelectorAll('.fixed.inset-0').forEach((n) => n.remove());
    });

    it('renders nothing when open=false', () => {
        const wrapper = mount(UploadDialog, {
            props: { open: false, uploadUrl: '/upload' },
            attachTo: document.body,
        });

        expect(document.querySelector('input[type="file"]')).toBeNull();
        wrapper.unmount();
    });

    it('renders the upload dialog when open=true', () => {
        const wrapper = mount(UploadDialog, {
            props: { open: true, uploadUrl: '/upload' },
            attachTo: document.body,
        });

        expect(document.querySelector('input[type="file"]')).not.toBeNull();
        wrapper.unmount();
    });

    it('respects the accept prop on the file input', () => {
        const wrapper = mount(UploadDialog, {
            props: { open: true, uploadUrl: '/upload', accept: 'image/*' },
            attachTo: document.body,
        });

        expect(document.querySelector('input[type="file"]')?.getAttribute('accept')).toBe('image/*');
        wrapper.unmount();
    });

    it('renders with multiple by default', () => {
        const wrapper = mount(UploadDialog, {
            props: { open: true, uploadUrl: '/upload' },
            attachTo: document.body,
        });

        const input = document.querySelector<HTMLInputElement>('input[type="file"]');
        expect(input?.multiple).toBe(true);
        wrapper.unmount();
    });

    it('emits update:open=false when the header close button is clicked', async () => {
        const wrapper = mount(UploadDialog, {
            props: { open: true, uploadUrl: '/upload' },
            attachTo: document.body,
        });

        // UploadDialog now renders via CommandDialog, whose header close button
        // carries the `cmd-dialog-close` class. Clicking it fires CommandDialog's
        // `update:visible=false`, which UploadDialog translates into
        // `update:open=false`.
        const closeBtn = document.querySelector<HTMLButtonElement>('.cmd-dialog-close');
        expect(closeBtn).not.toBeNull();
        closeBtn?.click();
        await wrapper.vm.$nextTick();

        const events = wrapper.emitted('update:open');
        expect(events?.some((e) => e[0] === false)).toBe(true);
        wrapper.unmount();
    });
});
