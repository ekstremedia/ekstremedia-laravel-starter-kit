import { ref, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import type { PageProps, UserSettings, UserSettingValue } from '@/types';

const STORAGE_KEY = import.meta.env.VITE_APP_STORAGE_KEY || 'starter_kit_settings';

// Default settings mirror UserSetting::$defaults on the backend
const defaults: UserSettings = {
    locale: 'en',
    dark_mode: false,
};

// Module-level reactive state — shared across all composable calls
const settings = ref<UserSettings>({ ...defaults });

let dbSyncTimer: ReturnType<typeof setTimeout> | null = null;
let initialized = false;

function sanitizePartial(partial: Partial<UserSettings>): Record<string, UserSettingValue> {
    return Object.fromEntries(
        Object.entries(partial).filter((entry): entry is [string, UserSettingValue] => entry[1] !== undefined),
    );
}

export function useSettings() {
    const page = usePage<PageProps>();

    function syncFromServer(serverSettings?: UserSettings) {
        settings.value = { ...defaults, ...serverSettings };
        localStorage.setItem(STORAGE_KEY, JSON.stringify(settings.value));
    }

    if (!initialized) {
        initialized = true;

        // 1. Load from localStorage immediately (fast, works for guests too)
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            try {
                settings.value = { ...defaults, ...JSON.parse(stored) };
            } catch {
                // Corrupt localStorage — fall back to defaults
            }
        }

        // Authenticated server settings are synced below via an immediate watch.
    }

    watch(
        () => [page.props.auth?.user?.id, page.props.settings] as const,
        ([userId, serverSettings]) => {
            if (userId && serverSettings) {
                syncFromServer(serverSettings);
            }
        },
        { deep: true, immediate: true },
    );

    /**
     * Update one or more settings.
     * Writes to localStorage immediately, then debounces a PATCH to the API.
     */
    function update(partial: Partial<UserSettings>) {
        const sanitizedPartial = sanitizePartial(partial);

        settings.value = { ...settings.value, ...sanitizedPartial };
        localStorage.setItem(STORAGE_KEY, JSON.stringify(settings.value));

        // Only sync to DB if the user is authenticated
        if (page.props.auth?.user) {
            if (dbSyncTimer) clearTimeout(dbSyncTimer);
            dbSyncTimer = setTimeout(() => {
                router.patch('/settings', sanitizedPartial, {
                    preserveState: true,
                    preserveScroll: true,
                });
            }, 600);
        }
    }

    return { settings, update };
}
