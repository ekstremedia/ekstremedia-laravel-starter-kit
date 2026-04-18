<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Customer } from '@/types';

defineProps<{
    customers: Customer[];
}>();
</script>

<template>
    <Head title="Choose where to continue" />
    <AppLayout>
        <div class="max-w-3xl mx-auto py-10 px-4 sm:px-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                Choose where to continue
            </h1>
            <p class="text-sm text-gray-500 dark:text-dark-400 mb-8">
                Your account has access to the following. Pick one to continue.
            </p>

            <div v-if="customers.length === 0"
                 class="rounded-xl border border-dashed border-gray-300 dark:border-dark-700 p-8 text-center">
                <i class="pi pi-building text-3xl text-gray-400 mb-3 block"></i>
                <p class="text-sm text-gray-600 dark:text-dark-400">
                    You aren't a member anywhere yet. Ask an administrator to add you.
                </p>
            </div>

            <ul v-else class="grid gap-3 sm:grid-cols-2">
                <li v-for="c in customers" :key="c.id">
                    <Link
                        :href="`/c/${c.slug}/dashboard`"
                        class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 dark:border-dark-700 bg-white dark:bg-dark-900 hover:border-indigo-500 hover:shadow-sm transition"
                    >
                        <div class="w-10 h-10 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-sm font-semibold">
                            {{ c.name.slice(0, 2).toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ c.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-dark-400 truncate">/c/{{ c.slug }}</p>
                        </div>
                    </Link>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
