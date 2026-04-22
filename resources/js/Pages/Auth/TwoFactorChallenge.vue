<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ref } from 'vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthCard from '@/Components/Command/AuthCard.vue';
import Field from '@/Components/Command/Field.vue';
import Icon from '@/Components/Command/Icon.vue';

defineOptions({ layout: AuthLayout });

const { t } = useI18n();

const useRecovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

function toggleRecovery() {
    useRecovery.value = !useRecovery.value;
    form.code = '';
    form.recovery_code = '';
    form.clearErrors();
}

function submit() {
    form.post('/two-factor-challenge');
}
</script>

<template>
    <Head :title="t('auth.two_factor_title')" />

    <AuthCard
        :eyebrow="t('auth.two_factor_title')"
        :title="useRecovery ? t('auth.two_factor_recovery_subtitle') : t('auth.two_factor_subtitle')"
    >
        <form @submit.prevent="submit" :style="{ display: 'flex', flexDirection: 'column', gap: '14px' }">
            <Field
                v-if="!useRecovery"
                v-model="form.code"
                :label="t('auth.two_factor_code')"
                :placeholder="t('auth.two_factor_code')"
                :error="form.errors.code"
                autocomplete="one-time-code"
                numeric
                autofocus
            />
            <Field
                v-else
                v-model="form.recovery_code"
                :label="t('auth.two_factor_recovery_code')"
                :placeholder="t('auth.two_factor_recovery_code')"
                :error="form.errors.recovery_code"
                autocomplete="off"
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
                <Icon name="key" :size="12" />
                {{ t('auth.two_factor_submit') }}
            </button>

            <button
                type="button"
                @click="toggleRecovery"
                :style="{
                    background: 'transparent',
                    border: 'none',
                    color: 'var(--accent)',
                    fontSize: '12px',
                    cursor: 'pointer',
                    fontFamily: 'inherit',
                    padding: '4px 0',
                }"
            >
                {{ useRecovery ? t('auth.two_factor_use_code') : t('auth.two_factor_use_recovery') }}
            </button>
        </form>
    </AuthCard>
</template>
