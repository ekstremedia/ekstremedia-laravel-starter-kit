<script setup lang="ts">
import { useForm, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ token: string; action: string }>();
const { t } = useI18n();

const form = useForm({ password: '' });

function submit() {
    form.post(props.action, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('share.title')" />
    <div class="flex min-h-screen items-center justify-center bg-slate-50 p-4 dark:bg-dark-950">
        <div class="w-full max-w-sm rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-dark-700 dark:bg-dark-900">
            <div class="mb-4 flex items-center gap-2 text-slate-700 dark:text-slate-200">
                <i class="pi pi-lock text-indigo-500" />
                <h1 class="text-lg font-semibold">{{ t('share.password_prompt') }}</h1>
            </div>
            <form @submit.prevent="submit" class="space-y-3">
                <input
                    v-model="form.password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    autofocus
                    :placeholder="t('share.enter_password')"
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-dark-700 dark:bg-dark-800 dark:text-slate-100"
                />
                <p v-if="form.errors.password" class="text-xs text-rose-500">{{ form.errors.password }}</p>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                >
                    {{ t('share.unlock') }}
                </button>
            </form>
        </div>
    </div>
</template>
