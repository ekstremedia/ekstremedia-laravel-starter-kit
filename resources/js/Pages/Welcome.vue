<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { PageProps } from '@/types';

const { t } = useI18n();
const page = usePage<PageProps>();
const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => user.value?.roles?.includes('Admin') ?? false);
const registrationOpen = computed(() => page.props.app_settings?.registration_open !== false);
const appName = import.meta.env.VITE_APP_NAME || t('app.name');

// When registration is disabled the primary CTA collapses to "Sign in" so the
// hero never dead-ends at a 403 "registration closed" page.
const primaryHref = computed(() => {
    if (user.value) return '/app';
    return registrationOpen.value ? '/register' : '/login';
});
const primaryLabel = computed(() => {
    if (user.value) return t('welcome.primary_cta_user');
    return registrationOpen.value ? t('welcome.primary_cta_guest') : t('nav.login');
});
const secondaryHref = computed(() => (user.value ? '/admin' : '/login'));
const secondaryLabel = computed(() =>
    user.value ? t('welcome.secondary_cta_user') : t('welcome.secondary_cta_guest'),
);
// For guests the secondary link duplicates the primary CTA once registration
// is closed (both point at /login), so hide it in that case.
const secondaryVisible = computed(() => {
    if (user.value) return isAdmin.value;
    return registrationOpen.value;
});

interface Feature {
    key: string;
    label: string;
    icon: string;
}

const features = computed<Feature[]>(() => [
    { key: 'auth', label: t('welcome.feature_auth'), icon: 'pi-lock' },
    { key: 'roles', label: t('welcome.feature_roles'), icon: 'pi-shield' },
    { key: 'tenancy', label: t('welcome.feature_tenancy'), icon: 'pi-building' },
    { key: 'queues', label: t('welcome.feature_queues'), icon: 'pi-bolt' },
    { key: 'observability', label: t('welcome.feature_observability'), icon: 'pi-chart-line' },
    { key: 'backups', label: t('welcome.feature_backups'), icon: 'pi-cloud-upload' },
]);
</script>

<template>
    <Head :title="t('nav.home')" />

    <AppLayout>
        <div class="min-h-[calc(100vh-4rem)] flex flex-col">
            <!-- Hero -->
            <section class="flex-1 flex items-center px-6">
                <div class="max-w-2xl mx-auto w-full py-20 md:py-28">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-gray-400 dark:text-dark-400 mb-6">
                        {{ t('welcome.eyebrow') }}
                    </p>
                    <h1 class="text-3xl md:text-5xl font-semibold tracking-tight text-gray-900 dark:text-white leading-[1.1] mb-6">
                        {{ t('welcome.hero_title') }}
                    </h1>
                    <p class="text-base md:text-lg text-gray-500 dark:text-dark-400 leading-relaxed mb-10 max-w-xl">
                        {{ t('welcome.hero_subtitle') }}
                    </p>

                    <div class="flex items-center gap-5">
                        <Link
                            :href="primaryHref"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-900 hover:bg-gray-800 dark:bg-white dark:hover:bg-gray-100 text-white dark:text-gray-900 text-sm font-medium rounded-lg transition-colors"
                        >
                            {{ primaryLabel }}
                            <i class="pi pi-arrow-right text-xs"></i>
                        </Link>
                        <Link
                            v-if="secondaryVisible"
                            :href="secondaryHref"
                            class="text-sm text-gray-600 dark:text-dark-300 hover:text-gray-900 dark:hover:text-white transition-colors"
                        >
                            {{ secondaryLabel }}
                        </Link>
                    </div>
                </div>
            </section>

            <!-- What's inside -->
            <section class="border-t border-gray-200/70 dark:border-dark-800 px-6">
                <div class="max-w-2xl mx-auto w-full py-12">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400 dark:text-dark-400 mb-6">
                        {{ t('welcome.included_title') }}
                    </h2>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3.5">
                        <li
                            v-for="feature in features"
                            :key="feature.key"
                            class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-200"
                        >
                            <i :class="['pi', feature.icon, 'text-gray-400 dark:text-dark-400 text-[13px] w-4']"></i>
                            <span>{{ feature.label }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Footer -->
            <footer class="border-t border-gray-200/70 dark:border-dark-800 px-6">
                <div class="max-w-2xl mx-auto w-full py-6 flex items-center justify-between text-xs text-gray-400 dark:text-dark-500">
                    <span>&copy; {{ new Date().getFullYear() }} {{ appName }}</span>
                    <span>v1</span>
                </div>
            </footer>
        </div>
    </AppLayout>
</template>
