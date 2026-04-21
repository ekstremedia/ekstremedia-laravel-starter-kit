<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{ links?: PaginationLink[] | null }>();
</script>

<template>
    <nav v-if="links && links.length > 3" class="mt-4 flex flex-wrap items-center gap-1 text-sm">
        <template v-for="link in links" :key="link.label">
            <Link
                v-if="link.url"
                :href="link.url"
                class="min-w-[2.25rem] h-9 inline-flex items-center justify-center px-3 rounded-lg border text-gray-600 dark:text-dark-300 transition-colors"
                :class="
                    link.active
                        ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                        : 'border-gray-200 dark:border-dark-700 hover:bg-gray-100 dark:hover:bg-dark-800'
                "
                v-html="link.label"
            />
            <span
                v-else
                class="min-w-[2.25rem] h-9 inline-flex items-center justify-center px-3 rounded-lg text-gray-400"
                v-html="link.label"
            ></span>
        </template>
    </nav>
</template>
