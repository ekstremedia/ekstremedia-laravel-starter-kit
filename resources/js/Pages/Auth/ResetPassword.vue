<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthCard from '@/Components/Command/AuthCard.vue';
import Field from '@/Components/Command/Field.vue';
import Icon from '@/Components/Command/Icon.vue';

defineOptions({ layout: AuthLayout });

const { t } = useI18n();

const props = defineProps<{ token: string; email: string }>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head :title="t('auth.reset_title')" />

    <AuthCard
        :eyebrow="t('auth.reset_title')"
        :title="t('auth.reset_subtitle')"
    >
        <form @submit.prevent="submit" :style="{ display: 'flex', flexDirection: 'column', gap: '14px' }">
            <Field
                v-model="form.email"
                type="email"
                :label="t('auth.email')"
                :error="form.errors.email"
                disabled
            />
            <Field
                v-model="form.password"
                type="password"
                :label="t('auth.password')"
                :placeholder="t('auth.password')"
                :error="form.errors.password"
                autocomplete="new-password"
                autofocus
            />
            <Field
                v-model="form.password_confirmation"
                type="password"
                :label="t('auth.password_confirmation')"
                :placeholder="t('auth.password_confirmation')"
                :error="form.errors.password_confirmation"
                autocomplete="new-password"
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
                {{ t('auth.reset_submit') }}
                <Icon name="arrow" :size="12" />
            </button>
        </form>
    </AuthCard>
</template>
