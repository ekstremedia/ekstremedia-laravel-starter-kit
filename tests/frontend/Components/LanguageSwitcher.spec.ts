import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { ref } from 'vue';

const currentLocale = ref('en');
const setLocale = vi.fn((code: string) => {
    currentLocale.value = code;
});
const locales = [
    { code: 'en', name: 'English', flag: '🇬🇧' },
    { code: 'no', name: 'Norsk', flag: '🇳🇴' },
];

vi.mock('@/composables/useLocale', () => ({
    useLocale: () => ({ currentLocale, setLocale, locales }),
}));

import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';

describe('LanguageSwitcher', () => {
    beforeEach(() => {
        currentLocale.value = 'en';
        setLocale.mockClear();
    });

    it('shows only the current flag in the trigger', () => {
        const wrapper = mount(LanguageSwitcher);
        const trigger = wrapper.get('button');
        expect(trigger.text()).toContain('🇬🇧');
        expect(trigger.text()).not.toContain('🇳🇴');
    });

    it('renders the dropdown with all locales after clicking the trigger', async () => {
        const wrapper = mount(LanguageSwitcher);
        await wrapper.get('button').trigger('click');

        const items = wrapper.findAll('button');
        const labels = items.map((b) => b.text());
        expect(labels.some((t) => t.includes('English'))).toBe(true);
        expect(labels.some((t) => t.includes('Norsk'))).toBe(true);
    });

    it('calls setLocale when a locale is picked', async () => {
        const wrapper = mount(LanguageSwitcher);
        await wrapper.get('button').trigger('click');

        const norskBtn = wrapper.findAll('button').find((b) => b.text().includes('Norsk'));
        expect(norskBtn).toBeDefined();
        await norskBtn!.trigger('click');

        expect(setLocale).toHaveBeenCalledWith('no');
    });

    it('marks the active locale with a checkmark', async () => {
        const wrapper = mount(LanguageSwitcher);
        await wrapper.get('button').trigger('click');

        const activeBtn = wrapper.findAll('button').find((b) => b.text().includes('English'));
        expect(activeBtn?.html()).toContain('pi-check');
    });
});
