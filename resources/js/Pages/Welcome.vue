<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { PageProps } from '@/types';

const { t } = useI18n();
const page = usePage<PageProps>();
const user = computed(() => page.props.auth?.user);
const appName = import.meta.env.VITE_APP_NAME || t('app.name');
</script>

<template>
    <Head :title="t('nav.home')" />

    <AppLayout>
        <!-- Hero -->
        <section class="py-16 md:py-24 px-4">
            <div class="max-w-3xl mx-auto text-center">
                <h1 class="text-4xl md:text-5xl font-bold mb-4 text-gray-900 dark:text-white leading-tight">
                    {{ t('welcome.hero_title') }}
                </h1>
                <p class="text-lg md:text-xl text-gray-500 dark:text-dark-400 mb-8 max-w-2xl mx-auto">
                    {{ t('welcome.hero_subtitle') }}
                </p>
                <Link
                    :href="user ? '/app' : '/register'"
                    class="inline-block px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition-colors"
                >
                    {{ user ? t('nav.dashboard') : t('welcome.get_started') }}
                </Link>
            </div>
        </section>

        <!-- Features -->
        <section class="py-16 px-4">
            <div class="max-w-5xl mx-auto">
                <h2 class="text-2xl font-bold text-center mb-10 text-gray-900 dark:text-white">
                    {{ t('welcome.features_title') }}
                </h2>

                <div class="grid md:grid-cols-3 gap-6">
                    <div class="p-6 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700 transition-colors">
                        <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-500/10 flex items-center justify-center mb-4">
                            <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold mb-2 text-gray-900 dark:text-white">
                            {{ t('welcome.feature_items') }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-dark-400">
                            {{ t('welcome.feature_items_desc') }}
                        </p>
                    </div>

                    <div class="p-6 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700 transition-colors">
                        <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-500/10 flex items-center justify-center mb-4">
                            <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold mb-2 text-gray-900 dark:text-white">
                            {{ t('welcome.feature_roles') }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-dark-400">
                            {{ t('welcome.feature_roles_desc') }}
                        </p>
                    </div>

                    <div class="p-6 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700 transition-colors">
                        <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-500/10 flex items-center justify-center mb-4">
                            <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold mb-2 text-gray-900 dark:text-white">
                            {{ t('welcome.feature_settings') }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-dark-400">
                            {{ t('welcome.feature_settings_desc') }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-8 px-4 text-center text-sm text-gray-400 dark:text-dark-500">
            <p>&copy; {{ new Date().getFullYear() }} {{ appName }}</p>
        </footer>
    </AppLayout>
</template>
