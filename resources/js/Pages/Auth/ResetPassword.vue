<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { onMounted, ref } from 'vue';
import { gsap } from 'gsap';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const { t } = useI18n();

const props = defineProps<{
    token: string;
    email: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
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

function submit() {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head :title="t('auth.reset_title')" />

    <AuthLayout>
        <div class="bg-white dark:bg-dark-900 rounded-2xl shadow-lg dark:shadow-dark-950/50 border border-gray-100 dark:border-dark-700 p-8 transition-colors">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('auth.reset_title') }}
                </h1>
                <p class="mt-2 text-gray-600 dark:text-dark-400">
                    {{ t('auth.reset_subtitle') }}
                </p>
            </div>

            <form @submit.prevent="submit">
                <div ref="formFields" class="space-y-5">
                    <TextInput
                        v-model="form.email"
                        type="email"
                        :label="t('auth.email')"
                        :error="form.errors.email"
                        disabled
                    />

                    <TextInput
                        v-model="form.password"
                        type="password"
                        :label="t('auth.password')"
                        :placeholder="t('auth.password')"
                        :error="form.errors.password"
                        autofocus
                    />

                    <TextInput
                        v-model="form.password_confirmation"
                        type="password"
                        :label="t('auth.password_confirmation')"
                        :placeholder="t('auth.password_confirmation')"
                        :error="form.errors.password_confirmation"
                    />

                    <PrimaryButton :disabled="form.processing">
                        {{ t('auth.reset_submit') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthLayout>
</template>
