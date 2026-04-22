<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthCard from '@/Components/Command/AuthCard.vue';
import Field from '@/Components/Command/Field.vue';
import Icon from '@/Components/Command/Icon.vue';

defineOptions({ layout: AuthLayout });

const { t } = useI18n();

const form = useForm({ password: '' });

function submit() {
    form.post('/user/confirm-password', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head :title="t('auth.confirm_password_title')" />

    <AuthCard
        :eyebrow="t('auth.confirm_password_title')"
        :title="t('auth.confirm_password_subtitle')"
    >
        <form @submit.prevent="submit" :style="{ display: 'flex', flexDirection: 'column', gap: '14px' }">
            <Field
                v-model="form.password"
                type="password"
                :label="t('auth.password')"
                :placeholder="t('auth.password')"
                :error="form.errors.password"
                autocomplete="current-password"
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
                {{ t('auth.confirm_password_submit') }}
                <Icon name="arrow" :size="12" />
            </button>
        </form>
    </AuthCard>
</template>
