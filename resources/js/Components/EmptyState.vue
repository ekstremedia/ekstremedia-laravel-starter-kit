<script setup lang="ts">
interface Props {
    icon?: string;
    title: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex flex-col items-center justify-center text-center px-6 py-12">
        <div
            v-if="icon"
            class="w-12 h-12 rounded-full bg-gray-100 dark:bg-dark-800 flex items-center justify-center text-gray-400 dark:text-gray-500 mb-4"
        >
            <i :class="['pi', icon, 'text-lg']"></i>
        </div>
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ title }}</h3>
        <p v-if="description" class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm">
            {{ description }}
        </p>
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>
