<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { onMounted, ref, computed } from 'vue';
import { gsap } from 'gsap';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import type { PageProps } from '@/types';

const { t } = useI18n();
const page = usePage<PageProps>();

const form = useForm({});
const iconRef = ref<HTMLElement>();
const sent = computed(() => page.props.flash?.success);

onMounted(() => {
    if (iconRef.value) {
        // Gentle floating animation on the mail icon
        gsap.to(iconRef.value, {
            y: -8,
            duration: 1.5,
            ease: 'power1.inOut',
            yoyo: true,
            repeat: -1,
        });
    }
});

function resend() {
    form.post('/email/verification-notification');
}
</script>

<template>
    <Head :title="t('auth.verify_title')" />

    <AuthLayout>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg dark:shadow-gray-900/50 p-8 transition-colors text-center">
            <!-- Animated mail icon -->
            <div ref="iconRef" class="text-6xl mb-6">
                ✉️
            </div>

            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">
                {{ t('auth.verify_title') }}
            </h1>

            <p class="text-gray-600 dark:text-gray-400 mb-2">
                {{ t('auth.verify_subtitle') }}
            </p>

            <p class="text-sm text-gray-500 dark:text-gray-500 mb-8">
                {{ t('auth.verify_check_spam') }}
            </p>

            <!-- Success message -->
            <div
                v-if="sent"
                class="mb-6 px-4 py-3 rounded-lg bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 text-sm
                       animate-fade-in"
            >
                {{ t('auth.verify_resent') }}
            </div>

            <form @submit.prevent="resend">
                <PrimaryButton :disabled="form.processing">
                    {{ t('auth.verify_resend') }}
                </PrimaryButton>
            </form>
        </div>
    </AuthLayout>
</template>
