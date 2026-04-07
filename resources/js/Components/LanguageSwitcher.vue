<script setup lang="ts">
import { useLocale } from '@/composables/useLocale';

const { currentLocale, setLocale, locales } = useLocale();
</script>

<template>
    <div class="flex items-center gap-1">
        <button
            v-for="loc in locales"
            :key="loc.code"
            @click="setLocale(loc.code)"
            class="text-2xl transition-transform hover:scale-110 cursor-pointer"
            :class="{ 'opacity-50 grayscale': currentLocale !== loc.code }"
            :title="loc.name"
            :aria-label="`Switch language to ${loc.name}`"
        >
            {{ loc.flag }}
        </button>
    </div>
</template>
