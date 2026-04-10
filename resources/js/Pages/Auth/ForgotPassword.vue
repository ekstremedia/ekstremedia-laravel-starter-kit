<script setup lang="ts">
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { onMounted, ref, computed } from 'vue';
import { gsap } from 'gsap';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import type { PageProps } from '@/types';

const { t } = useI18n();
const page = usePage<PageProps>();

const form = useForm({
    email: '',
});

const formFields = ref<HTMLElement>();
const status = computed(() => page.props.flash?.status);

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

function submit() {
    form.post('/forgot-password');
}
</script>

<template>
    <Head :title="t('auth.forgot_title')" />

    <AuthLayout>
        <div class="bg-white dark:bg-dark-900 rounded-2xl shadow-lg dark:shadow-dark-950/50 border border-gray-100 dark:border-dark-700 p-8 transition-colors">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('auth.forgot_title') }}
                </h1>
                <p class="mt-2 text-gray-600 dark:text-dark-400">
                    {{ t('auth.forgot_subtitle') }}
                </p>
            </div>

            <!-- Status message -->
            <div
                v-if="status"
                class="mb-6 px-4 py-3 rounded-lg bg-green-50 dark:bg-green-950/20 text-green-700 dark:text-green-400 text-sm"
            >
                {{ status }}
            </div>

            <form @submit.prevent="submit">
                <div ref="formFields" class="space-y-5">
                    <TextInput
                        v-model="form.email"
                        type="email"
                        :label="t('auth.email')"
                        :placeholder="t('auth.email')"
                        :error="form.errors.email"
                        autofocus
                    />

                    <PrimaryButton :disabled="form.processing">
                        {{ t('auth.forgot_send') }}
                    </PrimaryButton>
                </div>
            </form>

            <!-- Back to login -->
            <p class="mt-6 text-center text-sm text-gray-600 dark:text-dark-400">
                {{ t('auth.have_account') }}
                <Link href="/login" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                    {{ t('nav.login') }}
                </Link>
            </p>
        </div>
    </AuthLayout>
</template>
