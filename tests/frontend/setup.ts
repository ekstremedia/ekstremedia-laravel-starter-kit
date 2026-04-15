import { vi } from 'vitest';
import { config } from '@vue/test-utils';

// Minimal i18n stub so components calling t() don't crash
config.global.mocks = {
    $t: (key: string) => key,
};

// Silence Inertia if imported
vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual<any>('@inertiajs/vue3');
    return {
        ...actual,
        usePage: () => ({ props: { auth: { user: null }, settings: { locale: 'en', dark_mode: true }, flash: {}, debug: { easy_login_enabled: false }, locale: 'en' } }),
        router: { post: vi.fn(), delete: vi.fn(), get: vi.fn(), patch: vi.fn(), put: vi.fn() },
    };
});
