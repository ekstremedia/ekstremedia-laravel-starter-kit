import { computed, watch, onMounted } from 'vue';
import { useSettings } from './useSettings';

const storageKey = import.meta.env.VITE_APP_STORAGE_KEY || 'starter_kit_settings';

export function useDarkMode() {
    const { settings, update } = useSettings();

    const isDark = computed(() => settings.value.dark_mode);

    function applyTheme(dark: boolean) {
        document.documentElement.classList.toggle('dark', dark);
    }

    onMounted(() => {
        // If no saved setting, check system preference
        const stored = localStorage.getItem(storageKey);
        if (!stored) {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (prefersDark) update({ dark_mode: true });
        }
        applyTheme(isDark.value);
    });

    // Keep the DOM in sync whenever the setting changes
    watch(isDark, applyTheme);

    function toggle() {
        update({ dark_mode: !isDark.value });
    }

    return { isDark, toggle };
}
