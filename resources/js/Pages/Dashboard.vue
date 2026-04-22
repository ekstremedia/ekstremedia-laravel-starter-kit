<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLayout from '@/Layouts/CommandLayout.vue';
import Dot from '@/Components/Command/Dot.vue';
import Icon from '@/Components/Command/Icon.vue';
import { useCustomer } from '@/composables/useCustomer';
import { formatDate } from '@/composables/useDateTime';
import type { PageProps } from '@/types';

const { t } = useI18n();
const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user!);
const customer = computed(() => page.props.customer);
const { customerUrl } = useCustomer();
const profileUrl = computed(() => customerUrl('/profile'));
const primaryRole = computed(() => user.value.roles?.[0] ?? 'User');

function roleTone(r: string) {
    if (r === 'Admin') return { color: '#8b5cf6', bg: 'rgba(139,92,246,0.12)', border: 'rgba(139,92,246,0.33)' };
    if (r === 'Editor') return { color: 'var(--warning)', bg: 'rgba(251,191,36,0.12)', border: 'rgba(251,191,36,0.33)' };
    return { color: 'var(--accent)', bg: 'var(--accent-soft)', border: 'var(--accent-border)' };
}

const headerMeta = computed(() => {
    const now = new Date();
    const date = now.toLocaleDateString('nb-NO', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const time = now.toLocaleTimeString('nb-NO', { hour: '2-digit', minute: '2-digit', timeZoneName: 'short' });
    const tenant = customer.value?.name ?? '';
    return tenant ? `${date} · ${time} · ${tenant}` : `${date} · ${time}`;
});
</script>

<template>
    <AppLayout>
        <Head :title="t('nav.dashboard')" />

        <div :style="{ maxWidth: '780px', margin: '0 auto', padding: '32px 16px' }">
            <div
                class="cmd-mono"
                :style="{
                    fontSize: '10.5px',
                    color: 'var(--fg-mute)',
                    marginBottom: '10px',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '10px',
                }"
            >
                <span>{{ headerMeta }}</span>
                <span>·</span>
                <span :style="{ display: 'flex', alignItems: 'center', gap: '4px' }">
                    <Dot color="var(--success)" :size="5" />{{ t('home.all_operational') }}
                </span>
            </div>

            <h1
                :style="{
                    margin: 0,
                    fontSize: '32px',
                    fontWeight: 700,
                    letterSpacing: '-0.02em',
                    color: 'var(--fg)',
                }"
            >{{ t('dashboard.welcome', { name: user.first_name }) }}</h1>
            <p :style="{ fontSize: '13px', color: 'var(--fg-dim)', margin: '8px 0 0' }">
                {{ t('dashboard.subtitle') }}
            </p>

            <div
                :style="{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(2, 1fr)',
                    gap: '1px',
                    background: 'var(--border)',
                    border: '1px solid var(--border)',
                    borderRadius: 'var(--radius-card)',
                    marginTop: '24px',
                    overflow: 'hidden',
                }"
            >
                <div :style="{ background: 'var(--panel)', padding: '14px 16px' }">
                    <div
                        class="cmd-mono cmd-uc"
                        :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '8px', fontWeight: 500 }"
                    >{{ t('dashboard.role') }}</div>
                    <span
                        class="cmd-mono"
                        :style="{
                            fontSize: '11px',
                            color: roleTone(primaryRole).color,
                            background: roleTone(primaryRole).bg,
                            border: `1px solid ${roleTone(primaryRole).border}`,
                            padding: '2px 8px',
                            borderRadius: '3px',
                        }"
                    >{{ primaryRole }}</span>
                </div>

                <div :style="{ background: 'var(--panel)', padding: '14px 16px' }">
                    <div
                        class="cmd-mono cmd-uc"
                        :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '8px', fontWeight: 500 }"
                    >{{ t('dashboard.email_status') }}</div>
                    <div :style="{ display: 'flex', alignItems: 'center', gap: '7px' }">
                        <Dot :color="user.email_verified_at ? 'var(--success)' : 'var(--warning)'" :size="6" />
                        <span :style="{ fontSize: '13px', color: 'var(--fg)' }">
                            {{ user.email_verified_at ? t('dashboard.verified') : t('dashboard.unverified') }}
                        </span>
                    </div>
                </div>

                <div :style="{ background: 'var(--panel)', padding: '14px 16px' }">
                    <div
                        class="cmd-mono cmd-uc"
                        :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '8px', fontWeight: 500 }"
                    >{{ t('dashboard.two_factor_status') }}</div>
                    <div :style="{ display: 'flex', alignItems: 'center', gap: '7px' }">
                        <Dot :color="user.two_factor_enabled ? 'var(--success)' : 'var(--warning)'" :size="6" />
                        <span :style="{ fontSize: '13px', color: 'var(--fg)' }">
                            {{ user.two_factor_enabled ? t('dashboard.enabled') : t('dashboard.disabled') }}
                        </span>
                    </div>
                </div>

                <div :style="{ background: 'var(--panel)', padding: '14px 16px' }">
                    <div
                        class="cmd-mono cmd-uc"
                        :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '8px', fontWeight: 500 }"
                    >{{ t('dashboard.member_since') }}</div>
                    <div class="cmd-mono" :style="{ fontSize: '16px', color: 'var(--fg)' }">
                        {{ formatDate(user.created_at) }}
                    </div>
                </div>
            </div>

            <div :style="{ marginTop: '24px' }">
                <Link
                    :href="profileUrl"
                    :style="{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '6px',
                        background: 'var(--accent)',
                        color: '#fff',
                        padding: '7px 12px',
                        borderRadius: '5px',
                        fontSize: '12px',
                        fontWeight: 500,
                        textDecoration: 'none',
                    }"
                >
                    {{ t('dashboard.manage_profile') }}
                    <Icon name="arrow" :size="12" />
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
