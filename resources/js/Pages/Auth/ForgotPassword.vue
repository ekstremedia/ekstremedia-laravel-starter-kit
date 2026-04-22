<script setup lang="ts">
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthCard from '@/Components/Command/AuthCard.vue';
import Field from '@/Components/Command/Field.vue';
import Icon from '@/Components/Command/Icon.vue';
import Dot from '@/Components/Command/Dot.vue';
import type { PageProps } from '@/types';

defineOptions({ layout: AuthLayout });

const { t } = useI18n();
const page = usePage<PageProps>();

const form = useForm({ email: '' });
const status = computed(() => page.props.flash?.status);

function submit() {
    form.post('/forgot-password');
}
</script>

<template>
    <Head :title="t('auth.forgot_title')" />

    <AuthCard
        :eyebrow="t('auth.forgot_title')"
        :title="t('auth.forgot_subtitle')"
    >
        <div
            v-if="status"
            :style="{
                display: 'flex',
                alignItems: 'center',
                gap: '8px',
                padding: '9px 12px',
                marginBottom: '14px',
                borderRadius: '5px',
                background: 'rgba(94,229,154,0.12)',
                border: '1px solid rgba(94,229,154,0.33)',
                color: 'var(--fg)',
                fontSize: '12px',
            }"
        >
            <Dot color="var(--success)" :size="6" />
            <span>{{ status }}</span>
        </div>

        <form @submit.prevent="submit" :style="{ display: 'flex', flexDirection: 'column', gap: '14px' }">
            <Field
                v-model="form.email"
                type="email"
                :label="t('auth.email')"
                :placeholder="t('auth.email')"
                :error="form.errors.email"
                autocomplete="email"
                autofocus
            />
            <button
                type="submit"
                :disabled="form.processing"
                :style="{
                    background: 'var(--accent)',
                    color: '#fff',
                    border: 'none',
                    padding: '10px 14px',
                    borderRadius: '5px',
                    fontSize: '12.5px',
                    fontWeight: 500,
                    cursor: form.processing ? 'not-allowed' : 'pointer',
                    opacity: form.processing ? 0.6 : 1,
                    fontFamily: 'inherit',
                    display: 'inline-flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '6px',
                }"
            >
                {{ t('auth.forgot_send') }}
                <Icon name="arrow" :size="12" />
            </button>
        </form>

        <p :style="{ marginTop: '20px', textAlign: 'center', fontSize: '12px', color: 'var(--fg-dim)' }">
            {{ t('auth.have_account') }}
            <Link href="/login" :style="{ color: 'var(--accent)', textDecoration: 'none', fontWeight: 500 }">
                {{ t('nav.login') }}
            </Link>
        </p>
    </AuthCard>
</template>
