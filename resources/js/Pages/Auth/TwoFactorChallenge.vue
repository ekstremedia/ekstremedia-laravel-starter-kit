<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { onMounted, ref } from 'vue';
import { gsap } from 'gsap';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const { t } = useI18n();

const useRecovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const formFields = ref<HTMLElement>();

onMounted(() => {
    if (formFields.value) {
        gsap.from(formFields.value.children, {
            y: 15,
            opacity: 0,
            duration: 0.4,
            stagger: 0.08,
            ease: 'power2.out',
            delay: 0.3,
        });
    }
});

function toggleRecovery() {
    useRecovery.value = !useRecovery.value;
    form.code = '';
    form.recovery_code = '';
    form.clearErrors();
}

function submit() {
    form.post('/two-factor-challenge');
}
</script>

<template>
    <Head :title="t('auth.two_factor_title')" />

    <AuthLayout>
        <div class="bg-white dark:bg-dark-900 rounded-2xl shadow-lg dark:shadow-dark-950/50 border border-gray-100 dark:border-dark-700 p-8 transition-colors">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="text-5xl mb-4">🔐</div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('auth.two_factor_title') }}
                </h1>
                <p class="mt-2 text-gray-600 dark:text-dark-400">
                    {{ useRecovery ? t('auth.two_factor_recovery_subtitle') : t('auth.two_factor_subtitle') }}
                </p>
            </div>

            <form @submit.prevent="submit">
                <div ref="formFields" class="space-y-5">
                    <TextInput
                        v-if="!useRecovery"
                        v-model="form.code"
                        :label="t('auth.two_factor_code')"
                        :placeholder="t('auth.two_factor_code')"
                        :error="form.errors.code"
                        autofocus
                        autocomplete="one-time-code"
                        inputmode="numeric"
                    />

                    <TextInput
                        v-else
                        v-model="form.recovery_code"
                        :label="t('auth.two_factor_recovery_code')"
                        :placeholder="t('auth.two_factor_recovery_code')"
                        :error="form.errors.recovery_code"
                        autofocus
                        autocomplete="one-time-code"
                    />

                    <PrimaryButton :disabled="form.processing">
                        {{ t('auth.two_factor_submit') }}
                    </PrimaryButton>

                    <button
                        type="button"
                        class="w-full text-sm text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer"
                        @click="toggleRecovery"
                    >
                        {{ useRecovery ? t('auth.two_factor_use_code') : t('auth.two_factor_use_recovery') }}
                    </button>
                </div>
            </form>
        </div>
    </AuthLayout>
</template>
