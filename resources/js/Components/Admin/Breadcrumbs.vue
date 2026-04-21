<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

interface Crumb {
    label: string;
    href?: string;
}

defineProps<{ items: Crumb[] }>();
const { t } = useI18n();
</script>

<template>
    <nav v-if="items.length" :aria-label="t('common.breadcrumb')" class="mb-4">
        <ol class="flex flex-wrap items-center gap-1.5 text-xs text-gray-500 dark:text-dark-400">
            <template v-for="(crumb, i) in items" :key="i">
                <li class="inline-flex items-center">
                    <Link
                        v-if="crumb.href && i < items.length - 1"
                        :href="crumb.href"
                        class="hover:text-gray-900 dark:hover:text-white transition-colors"
                    >
                        {{ crumb.label }}
                    </Link>
                    <span v-else class="text-gray-700 dark:text-dark-200 font-medium">{{ crumb.label }}</span>
                </li>
                <li v-if="i < items.length - 1" aria-hidden="true">
                    <i class="pi pi-angle-right text-[10px] opacity-60"></i>
                </li>
            </template>
        </ol>
    </nav>
</template>
