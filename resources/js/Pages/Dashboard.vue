<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, onMounted, ref } from 'vue';
import { gsap } from 'gsap';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { PageProps } from '@/types';

const { t, locale } = useI18n();
const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user!);

const cardsRef = ref<HTMLElement>();

const roleBadgeClass = computed(() => {
    const role = user.value.roles?.[0];
    switch (role) {
        case 'Admin':
            return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-400';
        case 'Editor':
            return 'bg-teal-100 text-teal-700 dark:bg-teal-500/15 dark:text-teal-400';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-dark-800 dark:text-dark-400';
    }
});

onMounted(() => {
    if (cardsRef.value) {
        gsap.from(cardsRef.value.children, {
            y: 20,
            opacity: 0,
            duration: 0.4,
            stagger: 0.08,
            ease: 'power2.out',
            delay: 0.1,
        });
    }
});
</script>

<template>
    <Head :title="t('nav.dashboard')" />

    <AppLayout>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('dashboard.welcome', { name: user.first_name }) }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-dark-400">
                    {{ t('dashboard.subtitle') }}
                </p>
            </div>

            <!-- Stats cards -->
            <div ref="cardsRef" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Role -->
                <div class="p-5 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700">
                    <p class="text-xs font-medium text-gray-500 dark:text-dark-400 uppercase tracking-wider mb-2">
                        {{ t('dashboard.role') }}
                    </p>
                    <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full" :class="roleBadgeClass">
                        {{ user.roles?.[0] ?? 'User' }}
                    </span>
                </div>

                <!-- Email status -->
                <div class="p-5 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700">
                    <p class="text-xs font-medium text-gray-500 dark:text-dark-400 uppercase tracking-wider mb-2">
                        {{ t('dashboard.email_status') }}
                    </p>
                    <span
                        class="inline-flex items-center gap-1.5 text-sm font-medium"
                        :class="user.email_verified_at
                            ? 'text-green-600 dark:text-green-400'
                            : 'text-amber-600 dark:text-amber-400'"
                    >
                        <span class="w-2 h-2 rounded-full" :class="user.email_verified_at ? 'bg-green-500' : 'bg-amber-500'"></span>
                        {{ user.email_verified_at ? t('dashboard.verified') : t('dashboard.unverified') }}
                    </span>
                </div>

                <!-- 2FA status -->
                <div class="p-5 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700">
                    <p class="text-xs font-medium text-gray-500 dark:text-dark-400 uppercase tracking-wider mb-2">
                        {{ t('dashboard.two_factor_status') }}
                    </p>
                    <span
                        class="inline-flex items-center gap-1.5 text-sm font-medium"
                        :class="user.two_factor_enabled
                            ? 'text-green-600 dark:text-green-400'
                            : 'text-gray-500 dark:text-dark-400'"
                    >
                        <span class="w-2 h-2 rounded-full" :class="user.two_factor_enabled ? 'bg-green-500' : 'bg-gray-400 dark:bg-dark-500'"></span>
                        {{ user.two_factor_enabled ? t('dashboard.enabled') : t('dashboard.disabled') }}
                    </span>
                </div>

                <!-- Member since -->
                <div class="p-5 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700">
                    <p class="text-xs font-medium text-gray-500 dark:text-dark-400 uppercase tracking-wider mb-2">
                        {{ t('dashboard.member_since') }}
                    </p>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ user.created_at ? new Date(user.created_at).toLocaleDateString(locale) : '—' }}
                    </p>
                </div>
            </div>

            <!-- Quick link -->
            <div class="mt-6">
                <Link
                    href="/profile"
                    class="inline-flex items-center gap-2 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                >
                    {{ t('dashboard.manage_profile') }}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
