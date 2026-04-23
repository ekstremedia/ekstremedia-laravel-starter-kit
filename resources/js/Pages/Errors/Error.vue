<script setup lang="ts">
/*
 * Shared error page (403 / 404 / 419 / 500 / 503). Uses the Command shell
 * directly — no Rail (guests hit 404 too), just PublicTopbar + a
 * centered code + message + CTAs. Registered via Inertia exception
 * renderer in bootstrap/app.php.
 */
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useTweaks } from '@/composables/useTweaks';
import PublicTopbar from '@/Components/Command/PublicTopbar.vue';
import Icon from '@/Components/Command/Icon.vue';
import CmdButton from '@/Components/Command/Button.vue';
import type { PageProps } from '@/types';

interface Props {
    status: number;
    message?: string;
}
const props = withDefaults(defineProps<Props>(), { message: '' });

useTweaks();

const { t } = useI18n();
const page = usePage<PageProps>();
const user = computed(() => page.props.auth?.user);
const requestId = computed(() => (page.props as unknown as { request_id?: string }).request_id ?? '');

const titleKey = computed(() => {
    if (props.status === 403) return 'errors.403.title';
    if (props.status === 404) return 'errors.404.title';
    if (props.status === 419) return 'errors.419.title';
    if (props.status === 503) return 'errors.503.title';
    return 'errors.500.title';
});

const descriptionKey = computed(() => {
    if (props.status === 403) return 'errors.403.description';
    if (props.status === 404) return 'errors.404.description';
    if (props.status === 419) return 'errors.419.description';
    if (props.status === 503) return 'errors.503.description';
    return 'errors.500.description';
});

const pageTitle = computed(() => `${props.status} · ${t(titleKey.value)}`);

// Don't surface raw exception messages for server-side faults — they can leak
// internal stack detail (DB host, file path, etc.). Client-error statuses
// (403/404/419) use the message as-is so access-denied reasons like
// "You are not a member of [slug]." still reach the user.
const showRawMessage = computed(() => {
    const s = props.status;
    return s === 403 || s === 404 || s === 419;
});
const body = computed(() => (showRawMessage.value && props.message) ? props.message : t(descriptionKey.value));

function goBack() {
    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back();
    }
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
        <Head :title="pageTitle" />
        <PublicTopbar />

        <section :style="{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '0 24px' }">
            <div :style="{ maxWidth: '520px', width: '100%', textAlign: 'center', padding: '40px 0 60px' }">
                <div
                    class="cmd-mono cmd-uc"
                    :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '18px', letterSpacing: '0.08em', fontWeight: 500 }"
                >{{ t('errors.eyebrow') }}</div>

                <h1
                    class="cmd-mono"
                    :style="{
                        margin: 0,
                        fontSize: '92px',
                        fontWeight: 700,
                        lineHeight: 1,
                        color: 'var(--fg)',
                        letterSpacing: '-0.04em',
                    }"
                >{{ status }}</h1>

                <h2
                    :style="{
                        margin: '14px 0 0',
                        fontSize: '22px',
                        fontWeight: 600,
                        letterSpacing: '-0.02em',
                        color: 'var(--fg)',
                    }"
                >{{ t(titleKey) }}</h2>

                <p
                    :style="{
                        fontSize: '13.5px',
                        color: 'var(--fg-dim)',
                        margin: '10px auto 24px',
                        maxWidth: '420px',
                        lineHeight: 1.55,
                    }"
                >{{ body }}</p>

                <div :style="{ display: 'inline-flex', gap: '8px', flexWrap: 'wrap', justifyContent: 'center' }">
                    <CmdButton variant="ghost" size="md" @click="goBack">
                        <template #icon>
                            <Icon name="arrow" :size="12" :style="{ transform: 'rotate(180deg)' }" />
                        </template>
                        {{ t('errors.go_back') }}
                    </CmdButton>
                    <Link :href="user ? '/home' : '/'" :style="{ textDecoration: 'none' }">
                        <CmdButton variant="primary" size="md">
                            <template #icon><Icon name="home" :size="12" /></template>
                            {{ user ? t('errors.go_home') : t('errors.go_welcome') }}
                        </CmdButton>
                    </Link>
                    <Link v-if="!user" href="/login" :style="{ textDecoration: 'none' }">
                        <CmdButton variant="ghost" size="md">
                            <template #icon><Icon name="key" :size="12" /></template>
                            {{ t('nav.login') }}
                        </CmdButton>
                    </Link>
                </div>

                <p
                    v-if="requestId"
                    class="cmd-mono"
                    :style="{ marginTop: '32px', fontSize: '10.5px', color: 'var(--fg-mute)' }"
                >
                    {{ t('errors.request_id', { id: requestId }) }}
                </p>
            </div>
        </section>
    </div>
</template>
