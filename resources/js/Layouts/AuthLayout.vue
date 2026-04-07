<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { gsap } from 'gsap';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import DarkModeToggle from '@/Components/DarkModeToggle.vue';

const { t } = useI18n();
const cardRef = ref<HTMLElement>();
const appName = import.meta.env.VITE_APP_NAME || t('app.name');

onMounted(() => {
    if (cardRef.value) {
        gsap.from(cardRef.value, {
            y: 30,
            opacity: 0,
            duration: 0.6,
            ease: 'power3.out',
        });
    }
});
</script>

<template>
    <div class="min-h-screen flex flex-col bg-gray-50 dark:bg-gray-900 transition-colors">
        <!-- Top bar -->
        <div class="flex justify-between items-center p-4">
            <a href="/" class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 transition-colors hover:text-indigo-500">
                {{ appName }}
            </a>
            <div class="flex items-center gap-3">
                <LanguageSwitcher />
                <DarkModeToggle />
            </div>
        </div>

        <!-- Centered card -->
        <div class="flex-1 flex items-center justify-center px-4 pb-16">
            <div ref="cardRef" class="w-full max-w-md">
                <slot />
            </div>
        </div>
    </div>
</template>
