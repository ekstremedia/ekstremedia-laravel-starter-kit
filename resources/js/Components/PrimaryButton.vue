<script setup lang="ts">
defineProps<{
    disabled?: boolean;
    type?: 'submit' | 'button';
}>();
</script>

<template>
    <button
        :type="type ?? 'submit'"
        :disabled="disabled"
        class="w-full px-6 py-3 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800
               text-white font-semibold rounded-lg
               transition-all duration-200
               focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2
               dark:focus:ring-offset-gray-800
               disabled:opacity-50 disabled:cursor-not-allowed
               transform active:scale-[0.98] cursor-pointer"
    >
        <slot />
    </button>
</template>
