<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useLocale } from '@/composables/useLocale';

const { currentLocale, setLocale, locales } = useLocale();

const open = ref(false);
const current = computed(() => locales.find((l) => l.code === currentLocale.value) ?? locales[0]);

function toggle() {
    open.value = !open.value;
}

function pick(code: string) {
    setLocale(code);
    open.value = false;
}

function onClickOutside(e: MouseEvent) {
    const el = e.target as HTMLElement;
    if (!el.closest('[data-lang-switcher]')) open.value = false;
}

onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>

<template>
    <div class="relative" data-lang-switcher>
        <button
            @click="toggle"
            class="flex items-center gap-1 px-2 py-1.5 rounded-lg transition-colors hover:bg-gray-100 dark:hover:bg-dark-800 cursor-pointer"
            :title="current.name"
            :aria-label="`Current language: ${current.name}`"
        >
            <span class="text-xl leading-none">{{ current.flag }}</span>
            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute right-0 mt-2 w-40 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700 shadow-lg py-1 z-50"
            >
                <button
                    v-for="loc in locales"
                    :key="loc.code"
                    @click="pick(loc.code)"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-800 cursor-pointer"
                    :class="{ 'bg-gray-50 dark:bg-dark-800 font-medium': loc.code === currentLocale }"
                >
                    <span class="text-lg">{{ loc.flag }}</span>
                    <span>{{ loc.name }}</span>
                    <i v-if="loc.code === currentLocale" class="pi pi-check text-xs text-indigo-500 ml-auto"></i>
                </button>
            </div>
        </Transition>
    </div>
</template>
