import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { ref } from 'vue';

const isDark = ref(true);
const toggle = vi.fn(() => {
    isDark.value = !isDark.value;
});

vi.mock('@/composables/useDarkMode', () => ({
    useDarkMode: () => ({ isDark, toggle }),
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({ t: (k: string) => k }),
}));

import DarkModeToggle from '@/Components/DarkModeToggle.vue';

describe('DarkModeToggle', () => {
    beforeEach(() => {
        isDark.value = true;
        toggle.mockClear();
    });

    it('shows the sun icon when in dark mode', () => {
        const wrapper = mount(DarkModeToggle);
        const paths = wrapper.findAll('path');
        expect(paths.length).toBeGreaterThan(0);
        expect(wrapper.html()).toContain('text-yellow-400');
    });

    it('shows the moon icon when in light mode', async () => {
        isDark.value = false;
        const wrapper = mount(DarkModeToggle);
        expect(wrapper.html()).toContain('text-gray-700');
    });

    it('calls toggle on click', async () => {
        const wrapper = mount(DarkModeToggle);
        await wrapper.get('button').trigger('click');
        expect(toggle).toHaveBeenCalledOnce();
    });

    it('sets an accessible aria-label', () => {
        const wrapper = mount(DarkModeToggle);
        expect(wrapper.get('button').attributes('aria-label')).toBe('common.light_mode');
    });
});
