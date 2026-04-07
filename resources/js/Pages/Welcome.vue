<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t } = useI18n();
const appName = import.meta.env.VITE_APP_NAME || t('app.name');
</script>

<template>
    <Head :title="t('nav.home')" />

    <AppLayout>
        <!-- Hero section -->
        <section class="py-20 px-4">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-5xl md:text-6xl font-bold mb-6 text-gray-900 dark:text-white">
                    {{ t('welcome.hero_title') }}
                </h1>
                <p class="text-xl md:text-2xl text-gray-600 dark:text-gray-300 mb-10">
                    {{ t('welcome.hero_subtitle') }}
                </p>
                <a
                    href="#features"
                    class="inline-block px-8 py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition-colors text-lg"
                >
                    {{ t('welcome.get_started') }}
                </a>
            </div>
        </section>

        <!-- Features section -->
        <section id="features" class="py-16 px-4 bg-white dark:bg-gray-800 transition-colors">
            <div class="max-w-6xl mx-auto">
                <h2 class="text-3xl font-bold text-center mb-12 text-gray-900 dark:text-white">
                    {{ t('welcome.features_title') }}
                </h2>

                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Authentication feature -->
                    <div class="p-6 rounded-xl bg-gray-50 dark:bg-gray-700 transition-colors">
                        <div class="text-4xl mb-4">📦</div>
                        <h3 class="text-xl font-semibold mb-2 text-gray-900 dark:text-white">
                            {{ t('welcome.feature_items') }}
                        </h3>
                        <p class="text-gray-600 dark:text-gray-300">
                            {{ t('welcome.feature_items_desc') }}
                        </p>
                    </div>

                    <!-- Roles feature -->
                    <div class="p-6 rounded-xl bg-gray-50 dark:bg-gray-700 transition-colors">
                        <div class="text-4xl mb-4">💊</div>
                        <h3 class="text-xl font-semibold mb-2 text-gray-900 dark:text-white">
                            {{ t('welcome.feature_medicine') }}
                        </h3>
                        <p class="text-gray-600 dark:text-gray-300">
                            {{ t('welcome.feature_medicine_desc') }}
                        </p>
                    </div>

                    <!-- Settings feature -->
                    <div class="p-6 rounded-xl bg-gray-50 dark:bg-gray-700 transition-colors">
                        <div class="text-4xl mb-4">💰</div>
                        <h3 class="text-xl font-semibold mb-2 text-gray-900 dark:text-white">
                            {{ t('welcome.feature_economy') }}
                        </h3>
                        <p class="text-gray-600 dark:text-gray-300">
                            {{ t('welcome.feature_economy_desc') }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-8 px-4 text-center text-gray-500 dark:text-gray-400">
            <p>&copy; {{ new Date().getFullYear() }} {{ appName }}. {{ t('app.tagline') }}.</p>
        </footer>
    </AppLayout>
</template>
