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

        // The active row is the only one that renders the <Icon name="check"> — a
        // small SVG with this distinctive path. We assert on the path d-value
        // rather than a class name so the test doesn't break the next time the
        // icon's presentational wrapper changes.
        const activeBtn = wrapper.findAll('button').find((b) => b.text().includes('English'));
        const inactiveBtn = wrapper.findAll('button').find((b) => b.text().includes('Norsk'));
        expect(activeBtn?.find('svg').exists()).toBe(true);
        expect(activeBtn?.html()).toContain('M3 8l3 3 7-7');
        expect(inactiveBtn?.find('svg').exists()).toBe(false);
    });
});
