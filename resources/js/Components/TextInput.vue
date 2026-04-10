<script setup lang="ts">
import { ref, onMounted } from 'vue';

const props = defineProps<{
    modelValue: string;
    type?: string;
    placeholder?: string;
    label?: string;
    error?: string;
    autofocus?: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const inputRef = ref<HTMLInputElement>();
const isFocused = ref(false);

onMounted(() => {
    if (props.autofocus) {
        inputRef.value?.focus();
    }
});
</script>

<template>
    <div class="space-y-1">
        <label v-if="label" class="block text-sm font-medium text-gray-700 dark:text-gray-300 transition-colors">
            {{ label }}
        </label>
        <div class="relative">
            <input
                ref="inputRef"
                :type="type ?? 'text'"
                :value="modelValue"
                :placeholder="placeholder"
                :disabled="disabled"
                @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
                @focus="isFocused = true"
                @blur="isFocused = false"
                class="w-full px-4 py-3 rounded-lg border bg-white dark:bg-dark-800 text-gray-900 dark:text-gray-100
                       transition-all duration-200 outline-none
                       border-gray-300 dark:border-dark-600
                       focus:border-indigo-500 dark:focus:border-indigo-400
                       focus:ring-2 focus:ring-indigo-500/20 dark:focus:ring-indigo-400/20
                       placeholder:text-gray-400 dark:placeholder:text-dark-500
                       disabled:opacity-60 disabled:cursor-not-allowed"
                :class="{ '!border-red-500 !ring-red-500/20': error }"
            />
            <!-- Subtle glow on focus -->
            <div
                class="absolute inset-0 rounded-lg pointer-events-none transition-opacity duration-300"
                :class="isFocused ? 'opacity-100' : 'opacity-0'"
                style="box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.1), 0 0 15px -3px rgba(99, 102, 241, 0.15)"
            />
        </div>
        <p v-if="error" class="text-sm text-red-500 animate-shake">
            {{ error }}
        </p>
    </div>
</template>
