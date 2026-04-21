<script setup lang="ts">
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, onMounted, ref } from 'vue';
import { gsap } from 'gsap';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import type { PageProps } from '@/types';

const { t } = useI18n();
const page = usePage<PageProps>();
const oauthProviders = computed(() => page.props.oauth?.providers ?? []);

const providerIcons: Record<string, string> = { google: 'pi-google', github: 'pi-github' };
function providerIcon(name: string): string {
    return providerIcons[name] ?? 'pi-sign-in';
}
function providerLabel(name: string, fallback: string): string {
    const key = `auth.oauth.${name}`;
    const translated = t(key);
    return translated === key ? fallback : translated;
}

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
        <div class="bg-white dark:bg-dark-900 rounded-2xl shadow-lg dark:shadow-dark-950/50 border border-gray-100 dark:border-dark-700 p-8 transition-colors">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('auth.register_title') }}
                </h1>
                <p class="mt-2 text-gray-600 dark:text-dark-400">
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

            <div v-if="oauthProviders.length > 0" class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-200 dark:border-dark-700"></div>
                    </div>
                    <div class="relative flex justify-center text-xs uppercase tracking-wider">
                        <span class="bg-white dark:bg-dark-900 px-2 text-gray-400">
                            {{ t('auth.or_continue_with') }}
                        </span>
                    </div>
                </div>
                <div class="mt-4 grid gap-2" :class="oauthProviders.length > 1 ? 'grid-cols-2' : 'grid-cols-1'">
                    <a
                        v-for="p in oauthProviders"
                        :key="p.name"
                        :href="`/auth/${p.name}/redirect`"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-gray-200 dark:border-dark-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-dark-800 transition-colors"
                    >
                        <i :class="['pi', providerIcon(p.name), 'text-base']"></i>
                        <span>{{ providerLabel(p.name, p.label) }}</span>
                    </a>
                </div>
            </div>

            <!-- Login link -->
            <p class="mt-6 text-center text-sm text-gray-600 dark:text-dark-400">
                {{ t('auth.have_account') }}
                <Link href="/login" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                    {{ t('nav.login') }}
                </Link>
            </p>
        </div>
    </AuthLayout>
</template>
