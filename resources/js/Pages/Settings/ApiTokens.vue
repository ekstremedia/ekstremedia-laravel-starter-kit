<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/Layouts/CommandLayout.vue';
import Field from '@/Components/Command/Field.vue';
import Icon from '@/Components/Command/Icon.vue';
import Dot from '@/Components/Command/Dot.vue';
import { useCommandToasts } from '@/composables/useCommandToasts';
import type { PageProps } from '@/types';

interface Token {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    created_at: string | null;
}

const props = defineProps<{ tokens: Token[] }>();

const { t } = useI18n();
const page = usePage<PageProps & { flash: { new_token?: string } }>();
const { push } = useCommandToasts();
const confirmer = useConfirm();

const freshToken = computed(() => (page.props.flash?.new_token as string | undefined) ?? null);

const form = useForm({ name: '', abilities: [] as string[] });

function create() {
    form.post('/settings/tokens', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function revoke(token: Token) {
    confirmer.require({
        group: 'command',
        message: t('settings.tokens.confirm_revoke', { name: token.name }),
        header: t('settings.tokens.revoke'),
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        acceptLabel: t('settings.tokens.revoke'),
        rejectLabel: t('common.cancel'),
        accept: () => {
            router.delete(`/settings/tokens/${token.id}`, {
                preserveScroll: true,
                onSuccess: () => push(t('settings.tokens.toast_revoked', { name: token.name }), 'danger'),
            });
        },
    });
}

const copied = ref(false);
async function copyToClipboard(value: string) {
    if (!navigator.clipboard) return;
    try {
        await navigator.clipboard.writeText(value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch { /* user denied */ }
}

function relativeTime(iso: string | null): string {
    if (!iso) return t('settings.tokens.never_used');
    const timestamp = new Date(iso).getTime();
    if (Number.isNaN(timestamp)) return t('settings.tokens.never_used');
    const diff = Math.floor((Date.now() - timestamp) / 1000);
    if (diff < 60) return t('settings.tokens.seconds_ago', { n: diff });
    if (diff < 3600) return t('settings.tokens.minutes_ago', { n: Math.floor(diff / 60) });
    if (diff < 86400) return t('settings.tokens.hours_ago', { n: Math.floor(diff / 3600) });
    return t('settings.tokens.days_ago', { n: Math.floor(diff / 86400) });
}
</script>

<template>
    <Head :title="t('settings.tokens.title')" />

    <AppLayout>
        <section :style="{ maxWidth: '780px', margin: '0 auto', padding: '32px 16px', display: 'flex', flexDirection: 'column', gap: '20px' }">
            <header>
                <h1 :style="{ margin: 0, fontSize: '20px', fontWeight: 600, letterSpacing: '-0.01em', color: 'var(--fg)' }">
                    {{ t('settings.tokens.title') }}
                </h1>
                <p
                    class="cmd-mono"
                    :style="{ marginTop: '4px', fontSize: '11.5px', color: 'var(--fg-mute)' }"
                >{{ t('settings.tokens.description') }}</p>
            </header>

            <!-- Fresh token banner -->
            <div
                v-if="freshToken"
                :style="{
                    padding: '14px 16px',
                    borderRadius: 'var(--radius-card)',
                    background: 'rgba(94,229,154,0.10)',
                    border: '1px solid rgba(94,229,154,0.33)',
                }"
            >
                <div :style="{ display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '10px', fontSize: '11.5px', color: 'var(--success)', fontWeight: 500 }">
                    <Dot color="var(--success)" :size="5" />
                    {{ t('settings.tokens.new_token_hint') }}
                </div>
                <div :style="{ display: 'flex', gap: '6px', alignItems: 'stretch' }">
                    <code
                        class="cmd-mono"
                        :style="{
                            flex: 1,
                            overflowX: 'auto',
                            fontSize: '11px',
                            background: 'var(--panel2)',
                            border: '1px solid var(--border)',
                            borderRadius: '5px',
                            padding: '8px 10px',
                            color: 'var(--fg)',
                            whiteSpace: 'nowrap',
                        }"
                    >{{ freshToken }}</code>
                    <button
                        type="button"
                        @click="copyToClipboard(freshToken)"
                        :style="{
                            background: copied ? 'var(--success)' : 'var(--accent)',
                            color: copied ? '#0a0c12' : '#fff',
                            border: 'none',
                            padding: '6px 12px',
                            borderRadius: '5px',
                            fontSize: '11.5px',
                            fontWeight: 500,
                            cursor: 'pointer',
                            fontFamily: 'inherit',
                            whiteSpace: 'nowrap',
                        }"
                    >{{ copied ? t('settings.tokens.copied') : t('settings.tokens.copy') }}</button>
                </div>
            </div>

            <!-- Create form -->
            <form
                @submit.prevent="create"
                class="cmd-card"
                :style="{ padding: '20px', display: 'flex', flexDirection: 'column', gap: '14px' }"
            >
                <h2 :style="{ margin: 0, fontSize: '13px', fontWeight: 600, color: 'var(--fg)' }">
                    {{ t('settings.tokens.create_title') }}
                </h2>
                <Field
                    v-model="form.name"
                    :label="t('settings.tokens.name_label')"
                    :placeholder="t('settings.tokens.name_placeholder')"
                    :error="form.errors.name"
                />
                <p :style="{ fontSize: '11px', color: 'var(--fg-mute)', margin: 0 }">
                    {{ t('settings.tokens.abilities_hint') }}
                </p>
                <button
                    type="submit"
                    :disabled="form.processing || !form.name.trim()"
                    :style="{
                        alignSelf: 'flex-start',
                        background: 'var(--accent)',
                        color: '#fff',
                        border: 'none',
                        padding: '7px 12px',
                        borderRadius: '5px',
                        fontSize: '12px',
                        fontWeight: 500,
                        cursor: (form.processing || !form.name.trim()) ? 'not-allowed' : 'pointer',
                        opacity: (form.processing || !form.name.trim()) ? 0.55 : 1,
                        fontFamily: 'inherit',
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '6px',
                    }"
                >
                    <Icon name="plus" :size="12" />
                    {{ t('settings.tokens.create_button') }}
                </button>
            </form>

            <!-- Existing tokens -->
            <div class="cmd-card" :style="{ overflow: 'hidden' }">
                <header
                    :style="{
                        padding: '11px 16px',
                        borderBottom: '1px solid var(--border)',
                        fontSize: '12.5px',
                        fontWeight: 600,
                        color: 'var(--fg)',
                    }"
                >{{ t('settings.tokens.existing_title') }}</header>
                <ul
                    v-if="props.tokens.length"
                    :style="{ listStyle: 'none', padding: 0, margin: 0 }"
                >
                    <li
                        v-for="token in props.tokens"
                        :key="token.id"
                        :style="{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            gap: '16px',
                            padding: '12px 16px',
                            borderBottom: '1px solid var(--border)',
                        }"
                    >
                        <div :style="{ minWidth: 0 }">
                            <div :style="{ fontSize: '12.5px', fontWeight: 500, color: 'var(--fg)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }">
                                {{ token.name }}
                            </div>
                            <div
                                class="cmd-mono"
                                :style="{ fontSize: '10.5px', color: 'var(--fg-mute)', marginTop: '2px' }"
                            >
                                {{ t('settings.tokens.last_used') }}: {{ relativeTime(token.last_used_at) }}
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="revoke(token)"
                            :style="{ background: 'transparent', border: 'none', color: 'var(--danger)', cursor: 'pointer', fontSize: '11.5px', fontFamily: 'inherit' }"
                        >{{ t('settings.tokens.revoke') }}</button>
                    </li>
                </ul>
                <p
                    v-else
                    :style="{ padding: '24px 16px', margin: 0, fontSize: '12px', color: 'var(--fg-mute)', textAlign: 'center' }"
                >{{ t('settings.tokens.empty') }}</p>
            </div>
        </section>
    </AppLayout>
</template>
