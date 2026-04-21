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

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const easyLoginForm = useForm({});
const formFields = ref<HTMLElement>();

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
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}

function easyLogin() {
    easyLoginForm.post('/login/dev');
}
</script>

<template>
    <Head :title="t('nav.login')" />

    <AuthLayout>
        <div class="bg-white dark:bg-dark-900 rounded-2xl shadow-lg dark:shadow-dark-950/50 border border-gray-100 dark:border-dark-700 p-8 transition-colors">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('auth.login_title') }}
                </h1>
                <p class="mt-2 text-gray-600 dark:text-dark-400">
                    {{ t('auth.login_subtitle') }}
                </p>
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

                    <TextInput
                        v-model="form.password"
                        type="password"
                        :label="t('auth.password')"
                        :placeholder="t('auth.password')"
                        :error="form.errors.password"
                    />

                    <!-- Remember me & Forgot password -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-dark-600 text-indigo-600
                                       focus:ring-indigo-500 dark:bg-dark-800 transition-colors"
                            />
                            <span class="text-sm text-gray-600 dark:text-dark-400">
                                {{ t('auth.remember_me') }}
                            </span>
                        </label>

                        <Link
                            href="/forgot-password"
                            class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            {{ t('auth.forgot_password') }}
                        </Link>
                    </div>

                    <PrimaryButton :disabled="form.processing">
                        {{ t('nav.login') }}
                    </PrimaryButton>

                    <button
                        v-if="page.props.debug.easy_login_enabled"
                        type="button"
                        :disabled="easyLoginForm.processing"
                        class="w-full px-6 py-3 rounded-lg border border-dashed border-indigo-300 text-indigo-700
                               hover:bg-indigo-50 transition-colors cursor-pointer
                               dark:border-indigo-500/60 dark:text-indigo-300 dark:hover:bg-indigo-500/10
                               disabled:opacity-50 disabled:cursor-not-allowed"
                        @click="easyLogin"
                    >
                        {{ t('auth.easy_login') }}
                    </button>
                </div>
            </form>

            <!-- OAuth providers. Rendered only when SOCIALITE_ENABLED=true AND
                 at least one per-provider flag in config/socialite.php is on. -->
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

            <!-- Register link -->
            <p class="mt-6 text-center text-sm text-gray-600 dark:text-dark-400">
                {{ t('auth.no_account') }}
                <Link href="/register" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                    {{ t('nav.register') }}
                </Link>
            </p>
        </div>
    </AuthLayout>
</template>
