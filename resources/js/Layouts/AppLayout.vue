<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { usePage, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import DarkModeToggle from '@/Components/DarkModeToggle.vue';
import type { PageProps } from '@/types';

const { t } = useI18n();
const page = usePage<PageProps>();
const user = computed(() => page.props.auth?.user);
const appName = import.meta.env.VITE_APP_NAME || t('app.name');

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 transition-colors">
        <!-- Navigation -->
        <nav class="border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 transition-colors">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <Link href="/" class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 transition-colors hover:text-indigo-500">
                            {{ appName }}
                        </Link>
                    </div>

                    <!-- Right side -->
                    <div class="flex items-center gap-4">
                        <LanguageSwitcher />
                        <DarkModeToggle />

                        <!-- Auth links -->
                        <template v-if="user">
                            <span class="text-sm text-gray-600 dark:text-gray-400 hidden sm:inline">
                                {{ user.full_name }}
                            </span>
                            <button
                                @click="logout"
                                class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white
                                       transition-colors cursor-pointer"
                            >
                                {{ t('nav.logout') }}
                            </button>
                        </template>
                        <template v-else>
                            <Link
                                href="/login"
                                class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white
                                       transition-colors"
                            >
                                {{ t('nav.login') }}
                            </Link>
                            <Link
                                href="/register"
                                class="text-sm px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg
                                       transition-colors"
                            >
                                {{ t('nav.register') }}
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page content -->
        <main>
            <slot />
        </main>
    </div>
</template>
