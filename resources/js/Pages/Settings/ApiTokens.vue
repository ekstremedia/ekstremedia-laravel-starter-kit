<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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
const confirm = useConfirm();

// Flash-only: surface the plain-text token once, then let the next page
// visit clear it. The server never exposes it again.
const freshToken = computed(() => (page.props.flash?.new_token as string | undefined) ?? null);

const form = useForm({ name: '', abilities: [] as string[] });

function create() {
    form.post('/settings/tokens', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function revoke(token: Token) {
    confirm.require({
        group: 'api-tokens',
        header: t('common.confirm'),
        message: t('settings.tokens.confirm_revoke', { name: token.name }),
        acceptClass: 'p-button-danger',
        accept: () => router.delete(`/settings/tokens/${token.id}`, { preserveScroll: true }),
    });
}

const copied = ref(false);
function copyToClipboard(value: string) {
    navigator.clipboard?.writeText(value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
}

function relativeTime(iso: string | null): string {
    if (!iso) return t('settings.tokens.never_used');
    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (diff < 60) return t('settings.tokens.seconds_ago', { n: diff });
    if (diff < 3600) return t('settings.tokens.minutes_ago', { n: Math.floor(diff / 60) });
    if (diff < 86400) return t('settings.tokens.hours_ago', { n: Math.floor(diff / 3600) });
    return t('settings.tokens.days_ago', { n: Math.floor(diff / 86400) });
}
</script>

<template>
    <Head :title="t('settings.tokens.title')" />

    <AppLayout>
        <ConfirmDialog group="api-tokens" />
        <section class="max-w-3xl mx-auto py-10 px-4 space-y-8">
            <header>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ t('settings.tokens.title') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('settings.tokens.description') }}</p>
            </header>

            <!-- Freshly minted token — visible once. -->
            <div
                v-if="freshToken"
                class="p-4 rounded-xl border border-emerald-200 dark:border-emerald-500/40 bg-emerald-50 dark:bg-emerald-500/10"
            >
                <p class="text-sm font-medium text-emerald-900 dark:text-emerald-200 mb-2">
                    {{ t('settings.tokens.new_token_hint') }}
                </p>
                <div class="flex items-center gap-2">
                    <code class="flex-1 overflow-x-auto text-xs bg-white dark:bg-dark-900 border border-emerald-200 dark:border-emerald-500/40 rounded-lg px-3 py-2 font-mono text-emerald-900 dark:text-emerald-200">
                        {{ freshToken }}
                    </code>
                    <button
                        type="button"
                        class="px-3 py-2 rounded-lg text-xs font-medium bg-emerald-600 text-white hover:bg-emerald-700"
                        @click="copyToClipboard(freshToken)"
                    >
                        {{ copied ? t('settings.tokens.copied') : t('settings.tokens.copy') }}
                    </button>
                </div>
            </div>

            <!-- Create form -->
            <form @submit.prevent="create" class="p-5 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700 space-y-4">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('settings.tokens.create_title') }}</h2>
                <TextInput
                    v-model="form.name"
                    :label="t('settings.tokens.name_label')"
                    :placeholder="t('settings.tokens.name_placeholder')"
                    :error="form.errors.name"
                />
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ t('settings.tokens.abilities_hint') }}
                </p>
                <PrimaryButton :disabled="form.processing || !form.name.trim()">
                    {{ t('settings.tokens.create_button') }}
                </PrimaryButton>
            </form>

            <!-- Existing tokens -->
            <div class="rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700 overflow-hidden">
                <header class="px-5 py-3 border-b border-gray-200 dark:border-dark-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('settings.tokens.existing_title') }}</h2>
                </header>
                <ul v-if="props.tokens.length" class="divide-y divide-gray-100 dark:divide-dark-800">
                    <li v-for="token in props.tokens" :key="token.id" class="flex items-center justify-between px-5 py-3 gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ token.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ t('settings.tokens.last_used') }}: {{ relativeTime(token.last_used_at) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="text-xs text-red-500 hover:underline"
                            @click="revoke(token)"
                        >
                            {{ t('settings.tokens.revoke') }}
                        </button>
                    </li>
                </ul>
                <p v-else class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('settings.tokens.empty') }}
                </p>
            </div>
        </section>
    </AppLayout>
</template>
