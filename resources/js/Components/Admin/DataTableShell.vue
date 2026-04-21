<script setup lang="ts">
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';

interface Props {
    count?: number;
    countLabel?: string;
    searchPlaceholder?: string;
    searchValue?: string | null;
    hideSearch?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:searchValue': [value: string];
    'search-submit': [];
}>();

function onInput(event: Event) {
    emit('update:searchValue', (event.target as HTMLInputElement).value);
}
function onEnter() {
    emit('search-submit');
}
</script>

<template>
    <section class="bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl overflow-hidden">
        <div
            v-if="!hideSearch || $slots.filters || $slots.leading || typeof count === 'number'"
            class="flex flex-wrap items-center gap-3 px-4 py-3 border-b border-gray-200 dark:border-dark-800"
        >
            <div v-if="typeof count === 'number'" class="text-xs text-gray-500 dark:text-dark-400">
                <span class="font-medium text-gray-700 dark:text-gray-200">{{ count }}</span>
                <span v-if="countLabel" class="ml-1">{{ countLabel }}</span>
            </div>
            <slot name="leading" />
            <div class="flex-1"></div>
            <div v-if="$slots.filters" class="flex flex-wrap items-center gap-2">
                <slot name="filters" />
            </div>
            <IconField v-if="!hideSearch">
                <InputIcon class="pi pi-search" />
                <InputText
                    :value="searchValue ?? ''"
                    :placeholder="searchPlaceholder"
                    @input="onInput"
                    @keydown.enter="onEnter"
                    class="min-w-[14rem]"
                />
            </IconField>
        </div>
        <slot />
    </section>
</template>
