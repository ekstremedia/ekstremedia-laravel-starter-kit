<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { onMounted, ref } from 'vue';
import { gsap } from 'gsap';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const { t } = useI18n();

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
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
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head :title="t('nav.register')" />

    <AuthLayout>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg dark:shadow-gray-900/50 p-8 transition-colors">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('auth.register_title') }}
                </h1>
                <p class="mt-2 text-gray-600 dark:text-gray-400">
                    {{ t('auth.register_subtitle') }}
                </p>
            </div>

            <form @submit.prevent="submit">
                <div ref="formFields" class="space-y-5">
                    <!-- Name row -->
                    <div class="grid grid-cols-2 gap-4">
                        <TextInput
                            v-model="form.first_name"
                            :label="t('auth.first_name')"
                            :placeholder="t('auth.first_name')"
                            :error="form.errors.first_name"
                            autofocus
                        />
                        <TextInput
                            v-model="form.last_name"
                            :label="t('auth.last_name')"
                            :placeholder="t('auth.last_name')"
                            :error="form.errors.last_name"
                        />
                    </div>

                    <TextInput
                        v-model="form.email"
                        type="email"
                        :label="t('auth.email')"
                        :placeholder="t('auth.email')"
                        :error="form.errors.email"
                    />

                    <TextInput
                        v-model="form.password"
                        type="password"
                        :label="t('auth.password')"
                        :placeholder="t('auth.password')"
                        :error="form.errors.password"
                    />

                    <TextInput
                        v-model="form.password_confirmation"
                        type="password"
                        :label="t('auth.password_confirmation')"
                        :placeholder="t('auth.password_confirmation')"
                        :error="form.errors.password_confirmation"
                    />

                    <PrimaryButton :disabled="form.processing">
                        {{ t('nav.register') }}
                    </PrimaryButton>
                </div>
            </form>

            <!-- Login link -->
            <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
                {{ t('auth.have_account') }}
                <Link href="/login" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                    {{ t('nav.login') }}
                </Link>
            </p>
        </div>
    </AuthLayout>
</template>
