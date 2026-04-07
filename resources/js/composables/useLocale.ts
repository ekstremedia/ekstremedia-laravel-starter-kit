import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettings } from './useSettings';

export function useLocale() {
    const { locale } = useI18n();
    const { settings, update } = useSettings();

    const currentLocale = computed(() => settings.value.locale);

    function setLocale(newLocale: string) {
        locale.value = newLocale;
        document.documentElement.lang = newLocale;
        update({ locale: newLocale });
    }

    watch(
        currentLocale,
        (newLocale) => {
            if (locale.value !== newLocale) {
                locale.value = newLocale;
            }

            document.documentElement.lang = newLocale;
        },
        { immediate: true },
    );

    const locales = [
        { code: 'en', name: 'English', flag: '🇬🇧' },
        { code: 'no', name: 'Norsk', flag: '🇳🇴' },
    ];

    return { currentLocale, setLocale, locales };
}
