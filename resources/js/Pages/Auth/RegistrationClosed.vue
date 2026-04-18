<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <Head title="Registration closed" />
    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-dark-950 text-gray-900 dark:text-gray-100 p-6">
        <div class="max-w-md text-center">
            <i class="pi pi-lock text-5xl text-amber-500 mb-4"></i>
            <h1 class="text-2xl font-semibold mb-2">{{ t('auth.registration_closed') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                {{ t('auth.registration_closed_desc') }}
            </p>
            <Link href="/login" class="text-indigo-500 hover:underline text-sm">{{ t('auth.back_to_login') }}</Link>
        </div>
    </div>
</template>
