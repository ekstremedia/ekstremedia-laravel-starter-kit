import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import TextInput from '@/Components/TextInput.vue';

describe('TextInput', () => {
    it('renders the label and value', () => {
        const wrapper = mount(TextInput, {
            props: { modelValue: 'hello', label: 'Name' },
        });

        expect(wrapper.text()).toContain('Name');
        expect(wrapper.get('input').element.value).toBe('hello');
    });

    it('emits update:modelValue on input', async () => {
        const wrapper = mount(TextInput, { props: { modelValue: '' } });

        await wrapper.get('input').setValue('typed');

        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['typed']);
    });

    it('shows the error text and applies the error class', () => {
        const wrapper = mount(TextInput, {
            props: { modelValue: '', error: 'Required field' },
        });

        expect(wrapper.text()).toContain('Required field');
        expect(wrapper.get('input').classes().join(' ')).toContain('!border-red-500');
    });

    it('respects the disabled prop', () => {
        const wrapper = mount(TextInput, {
            props: { modelValue: '', disabled: true },
        });

        expect(wrapper.get('input').attributes('disabled')).toBeDefined();
    });

    it('defaults type to text and honors a custom type', () => {
        const text = mount(TextInput, { props: { modelValue: '' } });
        expect(text.get('input').attributes('type')).toBe('text');

        const email = mount(TextInput, { props: { modelValue: '', type: 'email' } });
        expect(email.get('input').attributes('type')).toBe('email');
    });
});
