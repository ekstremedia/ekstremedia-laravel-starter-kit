<script setup lang="ts">
/*
 * Auth layout — minimal Command-styled shell for login / register / reset /
 * verify / 2FA pages. No rail, no palette; just a logo + language toggle +
 * theme toggle at the top, centered content slot in the middle, and a mono
 * footer at the bottom. Applies Tweaks tokens so first paint honours the
 * user's saved theme/accent without authentication.
 */
import { useI18n } from 'vue-i18n';
import { Link } from '@inertiajs/vue3';
import { useTweaks } from '@/composables/useTweaks';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';

useTweaks();
const { t } = useI18n();
const appName = import.meta.env.VITE_APP_NAME || t('app.name');
const { state, setTheme } = useTweaks();

function cycleTheme() {
    setTheme(state.value.theme === 'light' ? 'dark' : 'light');
}
</script>

<template>
    <div
        class="cmd-shell"
        :style="{
            minHeight: '100vh',
            background: 'var(--bg)',
            color: 'var(--fg)',
            display: 'flex',
            flexDirection: 'column',
        }"
    >
        <!-- Top bar -->
        <header
            :style="{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                padding: '16px 24px',
            }"
        >
            <Link
                href="/"
                :style="{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: '10px',
                    textDecoration: 'none',
                    color: 'var(--fg)',
                }"
            >
                <span
                    :style="{
                        width: '26px',
                        height: '26px',
                        borderRadius: '5px',
                        background: 'var(--accent)',
                        color: '#fff',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        fontWeight: 700,
                        fontSize: '11px',
                        fontFamily: 'var(--font-mono)',
                    }"
                >SK</span>
                <span :style="{ fontSize: '13px', fontWeight: 600, letterSpacing: '-0.01em' }">{{ appName }}</span>
            </Link>
            <div :style="{ display: 'flex', alignItems: 'center', gap: '10px' }">
                <LanguageSwitcher />
                <button
                    type="button"
                    @click="cycleTheme"
                    :title="state.theme === 'light' ? t('topbar.menu.theme_dark') : t('topbar.menu.theme_light')"
                    :aria-label="state.theme === 'light' ? t('topbar.menu.theme_dark') : t('topbar.menu.theme_light')"
                    :style="{
                        background: 'transparent',
                        border: '1px solid var(--border)',
                        color: 'var(--fg-dim)',
                        padding: '4px 8px',
                        borderRadius: '5px',
                        cursor: 'pointer',
                        fontSize: '11.5px',
                        fontFamily: 'inherit',
                    }"
                >{{ state.theme === 'light' ? '🌙' : '☀︎' }}</button>
            </div>
        </header>

        <!-- Centered slot -->
        <main
            :style="{
                flex: 1,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: '24px 16px 48px',
            }"
        >
            <div
                :style="{
                    width: '100%',
                    maxWidth: '420px',
                    animation: 'cmdFadeIn 0.2s ease-out',
                }"
            >
                <slot />
            </div>
        </main>

        <!-- Footer -->
        <footer
            class="cmd-mono"
            :style="{
                padding: '16px 24px',
                display: 'flex',
                justifyContent: 'space-between',
                fontSize: '10.5px',
                color: 'var(--fg-mute)',
                borderTop: '1px solid var(--border)',
            }"
        >
            <span>© {{ new Date().getFullYear() }} {{ appName }}</span>
            <span>v1</span>
        </footer>
    </div>
</template>
