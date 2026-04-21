import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import ImageLightbox from '@/Components/Files/ImageLightbox.vue';
import type { LightboxItem } from '@/types/lightbox';

function items(): LightboxItem[] {
    return [
        { id: 1, src: '/one.jpg', thumb: '/one-t.jpg', name: 'one.jpg', canHaveTransparency: false },
        { id: 2, src: '/two.jpg', thumb: '/two-t.jpg', name: 'two.jpg', canHaveTransparency: true },
        { id: 3, src: '/three.jpg', thumb: '/three-t.jpg', name: 'three.jpg', canHaveTransparency: false },
    ] as LightboxItem[];
}

describe('ImageLightbox', () => {
    it('renders nothing when modelValue is null', () => {
        const wrapper = mount(ImageLightbox, {
            props: { modelValue: null, items: items() },
            attachTo: document.body,
        });

        expect(document.querySelector('.fixed.inset-0')).toBeNull();
        wrapper.unmount();
    });

    it('renders an overlay when an index is open', () => {
        const wrapper = mount(ImageLightbox, {
            props: { modelValue: 0, items: items() },
            attachTo: document.body,
        });

        expect(document.querySelector('.fixed.inset-0')).not.toBeNull();
        wrapper.unmount();
    });

    it('loads the current image src', () => {
        const wrapper = mount(ImageLightbox, {
            props: { modelValue: 1, items: items() },
            attachTo: document.body,
        });

        const img = document.querySelector('img');
        expect(img?.getAttribute('src')).toBe('/two.jpg');
        wrapper.unmount();
    });

    it('emits update:modelValue(null) when Escape is pressed', async () => {
        const wrapper = mount(ImageLightbox, {
            props: { modelValue: 0, items: items() },
            attachTo: document.body,
        });

        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null]);
        wrapper.unmount();
    });

    it('navigates to the next image with ArrowRight', async () => {
        const wrapper = mount(ImageLightbox, {
            props: { modelValue: 0, items: items() },
            attachTo: document.body,
        });

        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([1]);
        wrapper.unmount();
    });

    it('navigates to the previous image with ArrowLeft', async () => {
        const wrapper = mount(ImageLightbox, {
            props: { modelValue: 1, items: items() },
            attachTo: document.body,
        });

        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([0]);
        wrapper.unmount();
    });
});
