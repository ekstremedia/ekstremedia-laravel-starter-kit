import { mount } from '@vue/test-utils';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import VideoPlayer from '@/Components/Files/VideoPlayer.vue';

describe('VideoPlayer', () => {
    beforeEach(() => {
        // Videos try to auto-play on open; silence JSDOM's unsupported warning.
        Object.defineProperty(HTMLMediaElement.prototype, 'play', {
            configurable: true,
            value: vi.fn(() => Promise.resolve()),
        });
        Object.defineProperty(HTMLMediaElement.prototype, 'pause', {
            configurable: true,
            value: vi.fn(),
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('renders nothing when the dialog is closed', () => {
        const wrapper = mount(VideoPlayer, {
            props: { modelValue: false, src: '/clip.mp4' },
            attachTo: document.body,
        });

        expect(document.querySelector('[role="dialog"]')).toBeNull();
        wrapper.unmount();
    });

    it('renders nothing when src is null even if modelValue is true', () => {
        const wrapper = mount(VideoPlayer, {
            props: { modelValue: true, src: null },
            attachTo: document.body,
        });

        expect(document.querySelector('[role="dialog"]')).toBeNull();
        wrapper.unmount();
    });

    it('renders the dialog, title, and video when open', () => {
        const wrapper = mount(VideoPlayer, {
            props: { modelValue: true, src: '/clip.mp4', title: 'My clip' },
            attachTo: document.body,
        });

        const dialog = document.querySelector('[role="dialog"]');
        expect(dialog).not.toBeNull();
        expect(dialog?.textContent).toContain('My clip');
        expect(document.querySelector('video')).not.toBeNull();
        wrapper.unmount();
    });

    it('emits update:modelValue false when the close button is clicked', async () => {
        const wrapper = mount(VideoPlayer, {
            props: { modelValue: true, src: '/clip.mp4' },
            attachTo: document.body,
        });

        const closeBtn = document.querySelector('button[aria-label="common.close"]') as HTMLButtonElement | null;
        closeBtn?.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([false]);
        wrapper.unmount();
    });

    it('closes on Escape keydown', async () => {
        const wrapper = mount(VideoPlayer, {
            props: { modelValue: true, src: '/clip.mp4' },
            attachTo: document.body,
        });

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([false]);
        wrapper.unmount();
    });
});
