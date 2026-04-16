import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import PrimaryButton from '@/Components/PrimaryButton.vue';

describe('PrimaryButton', () => {
    it('renders slot content', () => {
        const wrapper = mount(PrimaryButton, { slots: { default: 'Save changes' } });
        expect(wrapper.text()).toBe('Save changes');
    });

    it('defaults to type="submit"', () => {
        const wrapper = mount(PrimaryButton);
        expect(wrapper.get('button').attributes('type')).toBe('submit');
    });

    it('forwards a custom type', () => {
        const wrapper = mount(PrimaryButton, { props: { type: 'button' } });
        expect(wrapper.get('button').attributes('type')).toBe('button');
    });

    it('is disabled when the prop is set', () => {
        const wrapper = mount(PrimaryButton, { props: { disabled: true } });
        expect(wrapper.get('button').attributes('disabled')).toBeDefined();
    });

    it('emits a click when pressed', async () => {
        const wrapper = mount(PrimaryButton);
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('click')).toHaveLength(1);
    });
});
