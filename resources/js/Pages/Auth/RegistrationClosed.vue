<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthCard from '@/Components/Command/AuthCard.vue';
import Dot from '@/Components/Command/Dot.vue';

defineOptions({ layout: AuthLayout });

const { t } = useI18n();
</script>

<template>
    <Head :title="t('auth.registration_closed')" />

    <AuthCard
        :eyebrow="t('auth.registration_closed')"
        :title="t('auth.registration_closed_desc')"
    >
        <div
            :style="{
                display: 'flex',
                alignItems: 'center',
                gap: '8px',
                padding: '9px 12px',
                marginBottom: '14px',
                borderRadius: '5px',
                background: 'rgba(251,191,36,0.12)',
                border: '1px solid rgba(251,191,36,0.33)',
                color: 'var(--fg)',
                fontSize: '12px',
            }"
        >
            <Dot color="var(--warning)" :size="6" />
            <span>{{ t('auth.registration_closed_message') }}</span>
        </div>

        <Link
            href="/login"
            :style="{
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                width: '100%',
                background: 'var(--accent)',
                color: '#fff',
                padding: '10px 14px',
                borderRadius: '5px',
                fontSize: '12.5px',
                fontWeight: 500,
                textDecoration: 'none',
                fontFamily: 'inherit',
            }"
        >{{ t('auth.back_to_login') }}</Link>
    </AuthCard>
</template>
