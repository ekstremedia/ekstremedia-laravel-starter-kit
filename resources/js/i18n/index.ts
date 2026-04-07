import { createI18n } from 'vue-i18n';
import en from './en';
import no from './no';

const storageKey = import.meta.env.VITE_APP_STORAGE_KEY || 'starter_kit_settings';

// Read locale from the unified settings store in localStorage
function getInitialLocale(): string {
    if (typeof window === 'undefined') return 'en';
    try {
        const stored = localStorage.getItem(storageKey);
        if (stored) return JSON.parse(stored).locale || 'en';
    } catch { /* ignore */ }
    return 'en';
}

export const i18n = createI18n({
    legacy: false,
    locale: getInitialLocale(),
    fallbackLocale: 'en',
    messages: { en, no },
});
