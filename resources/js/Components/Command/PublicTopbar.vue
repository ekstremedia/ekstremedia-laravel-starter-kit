<script setup lang="ts">
/*
 * Slim unauthenticated top bar shared across the marketing page and error
 * pages. Mirrors the authenticated Topbar's font/border/feel but has no
 * breadcrumbs, search, or notifications — just the logo and login /
 * register (or the user pill for authenticated visitors). Keeps guests
 * visually tied to the app without forcing an empty Rail.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import type { PageProps } from '@/types';

const { t } = useI18n();
const page = usePage<PageProps>();
const user = computed(() => page.props.auth?.user);
const registrationOpen = computed(() => page.props.app_settings?.registration_open !== false);
const appName = import.meta.env.VITE_APP_NAME || t('app.name');
</script>

<template>
    <header
        :style="{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            height: '48px',
            padding: '0 24px',
            borderBottom: '1px solid var(--border)',
            background: 'var(--bg)',
            flexShrink: 0,
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
                class="cmd-mono"
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
                    class="cmd-mono"
                    :style="{
                        width: '20px',
                        height: '20px',
                        borderRadius: '3px',
                        background: 'var(--accent)',
                        color: '#fff',
                        fontSize: '9.5px',
                        fontWeight: 700,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }"
                >{{ ((user.first_name?.[0] ?? '') + (user.last_name?.[0] ?? '')).toUpperCase() }}</span>
                <span>{{ user.full_name }}</span>
            </Link>
        </div>
    </header>
</template>
