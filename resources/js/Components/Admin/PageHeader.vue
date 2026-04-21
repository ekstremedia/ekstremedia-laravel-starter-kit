<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <header class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ title }}</h1>
            <p v-if="description" class="text-sm text-gray-500 dark:text-dark-400 mt-1 max-w-2xl">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2 shrink-0">
            <slot name="actions" />
        </div>
    </header>
</template>
