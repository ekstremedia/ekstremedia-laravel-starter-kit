<script setup lang="ts">
/*
 * Public root page — guests see a marketing landing; authenticated users
 * see their name + quick CTAs to the app. Uses Command tokens directly
 * without the CommandLayout shell: guests have no rail to populate, and
 * authenticated users get clearer primary CTAs here than an empty shell.
 */
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, onMounted } from 'vue';
import { useTweaks } from '@/composables/useTweaks';
import Icon from '@/Components/Command/Icon.vue';
import Dot from '@/Components/Command/Dot.vue';
import type { PageProps } from '@/types';

// Apply tweaks tokens even for unauthenticated visitors so fonts/accent
// line up with the rest of the app.
useTweaks();

const { t } = useI18n();
const page = usePage<PageProps>();
const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => user.value?.roles?.includes('Admin') ?? false);
const registrationOpen = computed(() => page.props.app_settings?.registration_open !== false);
const appName = import.meta.env.VITE_APP_NAME || t('app.name');

const primaryHref = computed(() => {
    if (user.value) return '/home';
    return registrationOpen.value ? '/register' : '/login';
});
const primaryLabel = computed(() => {
    if (user.value) return t('welcome.primary_cta_user');
    return registrationOpen.value ? t('welcome.primary_cta_guest') : t('nav.login');
});
const secondaryHref = computed(() => (user.value ? (isAdmin.value ? '/admin' : '/app') : '/login'));
const secondaryLabel = computed(() =>
    user.value ? t('welcome.secondary_cta_user') : t('welcome.secondary_cta_guest'),
);
const secondaryVisible = computed(() => {
    if (user.value) return true;
    return registrationOpen.value;
});

const chatEnabled = computed(() => page.props.chat?.enabled ?? false);
const customerSlug = computed(() => page.props.customer?.slug ?? null);
const filesEnabled = computed(() => page.props.customer?.files_feature_enabled ?? false);

interface QuickLink {
    key: string;
    href: string;
    label: string;
    hint: string;
    icon: 'home' | 'user' | 'users' | 'cog' | 'key' | 'role' | 'shield' | 'disk' | 'mail' | 'customer' | 'server' | 'log';
}

const quickLinks = computed<QuickLink[]>(() => {
    if (!user.value) {
        return [
            { key: 'login', href: '/login', label: t('welcome.quick.login'), hint: t('welcome.quick.login_hint'), icon: 'key' },
            { key: 'register', href: '/register', label: t('welcome.quick.register'), hint: registrationOpen.value ? t('welcome.quick.register_open_hint') : t('welcome.quick.register_closed_hint'), icon: 'user' },
            { key: 'forgot', href: '/forgot-password', label: t('welcome.quick.forgot'), hint: t('welcome.quick.forgot_hint'), icon: 'mail' },
        ];
    }

    const links: QuickLink[] = [
        { key: 'home', href: '/home', label: t('welcome.quick.home'), hint: t('welcome.quick.home_hint'), icon: 'user' },
    ];

    if (customerSlug.value) {
        links.push({ key: 'cdash', href: `/c/${customerSlug.value}/dashboard`, label: t('welcome.quick.customer_dashboard'), hint: page.props.customer?.name ?? t('welcome.quick.customer_dashboard_hint'), icon: 'home' });
        if (filesEnabled.value) {
            links.push({ key: 'files', href: `/c/${customerSlug.value}/files`, label: t('welcome.quick.files'), hint: t('welcome.quick.files_hint'), icon: 'disk' });
        }
    }
    if (chatEnabled.value) {
        links.push({ key: 'chat', href: '/chat', label: t('welcome.quick.chat'), hint: t('welcome.quick.chat_hint'), icon: 'mail' });
    }

    links.push(
        { key: 'profile', href: '/profile', label: t('welcome.quick.profile'), hint: t('welcome.quick.profile_hint'), icon: 'user' },
        { key: 'notif', href: '/settings/notifications', label: t('welcome.quick.notifications'), hint: t('welcome.quick.notifications_hint'), icon: 'cog' },
        { key: 'tokens', href: '/settings/tokens', label: t('welcome.quick.tokens'), hint: t('welcome.quick.tokens_hint'), icon: 'key' },
    );

    if (isAdmin.value) {
        links.push(
            { key: 'admin', href: '/admin', label: t('welcome.quick.admin'), hint: t('welcome.quick.admin_hint'), icon: 'home' },
            { key: 'users', href: '/admin/users', label: t('welcome.quick.users'), hint: t('welcome.quick.users_hint'), icon: 'users' },
            { key: 'settings', href: '/admin/settings', label: t('welcome.quick.settings'), hint: t('welcome.quick.settings_hint'), icon: 'cog' },
        );
    }

    return links;
});

const quickLinksTitle = computed(() => (user.value ? t('welcome.shortcuts_title') : t('welcome.get_started_title')));

const now = new Date().toLocaleDateString('nb-NO', { day: '2-digit', month: '2-digit', year: 'numeric' });
</script>

<template>
    <Head :title="t('nav.home')" />

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
        <!-- Minimal top bar: logo + auth shortcuts -->
        <header
            :style="{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                padding: '16px 24px',
                borderBottom: '1px solid var(--border)',
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

            <div :style="{ display: 'flex', alignItems: 'center', gap: '8px' }">
                <Link
                    v-if="!user"
                    href="/login"
                    :style="{
                        fontSize: '11.5px',
                        color: 'var(--fg-dim)',
                        padding: '5px 10px',
                        textDecoration: 'none',
                    }"
                >{{ t('nav.login') }}</Link>
                <Link
                    v-if="!user && registrationOpen"
                    href="/register"
                    :style="{
                        fontSize: '11.5px',
                        background: 'var(--accent)',
                        color: '#fff',
                        padding: '5px 11px',
                        borderRadius: '5px',
                        textDecoration: 'none',
                        fontWeight: 500,
                    }"
                >{{ t('nav.register') }}</Link>
                <Link
                    v-if="user"
                    href="/home"
                    :style="{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '7px',
                        padding: '4px 10px 4px 4px',
                        borderRadius: '5px',
                        background: 'var(--panel2)',
                        border: '1px solid var(--border)',
                        color: 'var(--fg)',
                        fontSize: '11.5px',
                        textDecoration: 'none',
                    }"
                >
                    <span
                        :style="{
                            width: '20px',
                            height: '20px',
                            borderRadius: '3px',
                            background: 'var(--accent)',
                            color: '#fff',
                            fontSize: '9.5px',
                            fontWeight: 700,
                            fontFamily: 'var(--font-mono)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                        }"
                    >{{ ((user.first_name?.[0] ?? '') + (user.last_name?.[0] ?? '')).toUpperCase() }}</span>
                    <span>{{ user.full_name }}</span>
                </Link>
            </div>
        </header>

        <!-- Hero -->
        <section :style="{ flex: 1, display: 'flex', alignItems: 'center', padding: '0 24px' }">
            <div :style="{ maxWidth: '780px', margin: '0 auto', width: '100%', padding: '80px 0 60px' }">
                <div
                    class="cmd-mono cmd-uc"
                    :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '24px', fontWeight: 500, display: 'inline-flex', alignItems: 'center', gap: '8px' }"
                >
                    <Dot color="var(--success)" :size="5" />
                    <span>{{ now }} · {{ t('welcome.eyebrow') }}</span>
                </div>

                <h1
                    :style="{
                        margin: 0,
                        fontSize: '44px',
                        fontWeight: 700,
                        letterSpacing: '-0.025em',
                        lineHeight: 1.05,
                        color: 'var(--fg)',
                    }"
                >{{ user ? t('welcome.hero_title_user', { name: user.first_name }) : t('welcome.hero_title') }}</h1>

                <p
                    :style="{
                        fontSize: '14px',
                        color: 'var(--fg-dim)',
                        margin: '14px 0 32px',
                        maxWidth: '560px',
                        lineHeight: 1.55,
                    }"
                >{{ t('welcome.hero_subtitle') }}</p>

                <div :style="{ display: 'flex', alignItems: 'center', gap: '8px' }">
                    <Link
                        :href="primaryHref"
                        :style="{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: '6px',
                            background: 'var(--accent)',
                            color: '#fff',
                            padding: '8px 14px',
                            borderRadius: '5px',
                            fontSize: '12.5px',
                            fontWeight: 500,
                            textDecoration: 'none',
                        }"
                    >
                        {{ primaryLabel }}
                        <Icon name="arrow" :size="12" />
                    </Link>
                    <Link
                        v-if="secondaryVisible"
                        :href="secondaryHref"
                        :style="{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: '6px',
                            background: 'transparent',
                            color: 'var(--fg-dim)',
                            border: '1px solid var(--border)',
                            padding: '8px 14px',
                            borderRadius: '5px',
                            fontSize: '12.5px',
                            textDecoration: 'none',
                            fontFamily: 'inherit',
                        }"
                    >{{ secondaryLabel }}</Link>
                </div>
            </div>
        </section>

        <!-- Quick-links grid -->
        <section :style="{ borderTop: '1px solid var(--border)', padding: '0 24px', background: 'var(--bg2)' }">
            <div :style="{ maxWidth: '780px', margin: '0 auto', width: '100%', padding: '32px 0' }">
                <div
                    class="cmd-mono cmd-uc"
                    :style="{ fontSize: '10px', color: 'var(--fg-mute)', fontWeight: 500, marginBottom: '20px' }"
                >{{ quickLinksTitle }}</div>

                <div
                    :style="{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))',
                        gap: '1px',
                        background: 'var(--border)',
                        border: '1px solid var(--border)',
                        borderRadius: '6px',
                        overflow: 'hidden',
                    }"
                >
                    <Link
                        v-for="l in quickLinks"
                        :key="l.key"
                        :href="l.href"
                        class="cmd-quick-link"
                        :style="{
                            display: 'flex',
                            alignItems: 'flex-start',
                            gap: '11px',
                            padding: '14px 16px',
                            background: 'var(--panel)',
                            textDecoration: 'none',
                            transition: 'background 0.12s',
                        }"
                    >
                        <span
                            :style="{
                                width: '26px',
                                height: '26px',
                                borderRadius: '5px',
                                background: 'var(--accent-soft)',
                                border: '1px solid var(--accent-border)',
                                color: 'var(--accent)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                flexShrink: 0,
                            }"
                        >
                            <Icon :name="l.icon" :size="14" />
                        </span>
                        <span :style="{ display: 'flex', flexDirection: 'column', gap: '2px', minWidth: 0 }">
                            <span :style="{ fontSize: '12.5px', fontWeight: 500, color: 'var(--fg)' }">{{ l.label }}</span>
                            <span :style="{ fontSize: '11px', color: 'var(--fg-dim)', overflow: 'hidden', textOverflow: 'ellipsis' }">{{ l.hint }}</span>
                        </span>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer :style="{ borderTop: '1px solid var(--border)', padding: '0 24px', background: 'var(--bg)' }">
            <div
                class="cmd-mono"
                :style="{
                    maxWidth: '780px',
                    margin: '0 auto',
                    width: '100%',
                    padding: '16px 0',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    fontSize: '10.5px',
                    color: 'var(--fg-mute)',
                }"
            >
                <span>© {{ new Date().getFullYear() }} {{ appName }}</span>
                <span>v1</span>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.cmd-quick-link:hover {
    background: var(--panel2) !important;
}
</style>
