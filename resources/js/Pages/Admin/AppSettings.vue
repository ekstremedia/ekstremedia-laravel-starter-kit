<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputText from 'primevue/inputtext';
import ToggleSwitch from 'primevue/toggleswitch';
import Select from 'primevue/select';
import Button from 'primevue/button';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface Settings {
    site_up: boolean;
    registration_open: boolean;
    login_enabled: boolean;
    require_email_verification: boolean;
    default_role: string;
    require_2fa_for_admins: boolean;
    send_welcome_notification: boolean;
    maintenance_message: string | null;
    announcement_banner: string | null;
    announcement_severity: 'info' | 'warn' | 'danger' | 'success';
}

const props = defineProps<{ settings: Settings; roles: string[] }>();

const form = useForm({ ...props.settings });

const severityOptions = [
    { label: 'Info', value: 'info' },
    { label: 'Warning', value: 'warn' },
    { label: 'Danger', value: 'danger' },
    { label: 'Success', value: 'success' },
];

function save() {
    form.patch('/admin/settings', { preserveScroll: true });
}
</script>

<template>
    <Head title="App Settings · Admin" />

    <div class="flex items-center justify-between mb-6">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-semibold">{{ t('admin.settings.title') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('admin.settings.desc') }}</p>
        </div>
    </div>

    <form @submit.prevent="save" class="max-w-3xl space-y-4">
        <!-- Access -->
        <section class="bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-2xl p-6 space-y-4">
            <h2 class="font-semibold flex items-center gap-2"><i class="pi pi-globe text-indigo-500"></i> {{ t('admin.settings.access') }}</h2>

            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">{{ t('admin.settings.site_up') }}</p>
                    <p class="text-xs text-gray-500">{{ t('admin.settings.site_up_desc') }}</p>
                </div>
                <ToggleSwitch v-model="form.site_up" />
            </div>

            <div class="min-w-0 flex-1">
                <label class="text-xs text-gray-500 uppercase tracking-wide">{{ t('admin.settings.maintenance_message') }}</label>
                <InputText v-model="form.maintenance_message" class="w-full" :placeholder="t('admin.settings.maintenance_placeholder')" />
            </div>

            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">{{ t('admin.settings.login_enabled') }}</p>
                    <p class="text-xs text-gray-500">{{ t('admin.settings.login_enabled_desc') }}</p>
                </div>
                <ToggleSwitch v-model="form.login_enabled" />
            </div>

            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">{{ t('admin.settings.registration_open') }}</p>
                    <p class="text-xs text-gray-500">{{ t('admin.settings.registration_open_desc') }}</p>
                </div>
                <ToggleSwitch v-model="form.registration_open" />
            </div>

            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">{{ t('admin.settings.require_verification') }}</p>
                    <p class="text-xs text-gray-500">{{ t('admin.settings.require_verification_desc') }}</p>
                </div>
                <ToggleSwitch v-model="form.require_email_verification" />
            </div>
        </section>

        <!-- Policies -->
        <section class="bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-2xl p-6 space-y-4">
            <h2 class="font-semibold flex items-center gap-2"><i class="pi pi-shield text-indigo-500"></i> {{ t('admin.settings.policies') }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="min-w-0 flex-1">
                    <label class="text-xs text-gray-500 uppercase tracking-wide">{{ t('admin.settings.default_role') }}</label>
                    <Select v-model="form.default_role" :options="roles" class="w-full" />
                </div>
            </div>

            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">{{ t('admin.settings.require_2fa_admin') }}</p>
                    <p class="text-xs text-gray-500">{{ t('admin.settings.require_2fa_admin_desc') }}</p>
                </div>
                <ToggleSwitch v-model="form.require_2fa_for_admins" />
            </div>

            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">{{ t('admin.settings.send_welcome') }}</p>
                    <p class="text-xs text-gray-500">{{ t('admin.settings.send_welcome_desc') }}</p>
                </div>
                <ToggleSwitch v-model="form.send_welcome_notification" />
            </div>
        </section>

        <!-- Announcements -->
        <section class="bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-2xl p-6 space-y-4">
            <h2 class="font-semibold flex items-center gap-2"><i class="pi pi-megaphone text-indigo-500"></i> {{ t('admin.settings.announcement') }}</h2>

            <div class="min-w-0 flex-1">
                <label class="text-xs text-gray-500 uppercase tracking-wide">{{ t('admin.settings.banner_text') }}</label>
                <InputText v-model="form.announcement_banner" class="w-full" :placeholder="t('admin.settings.banner_placeholder')" />
            </div>

            <div class="min-w-0 flex-1">
                <label class="text-xs text-gray-500 uppercase tracking-wide">{{ t('admin.settings.severity') }}</label>
                <Select v-model="form.announcement_severity" :options="severityOptions" optionLabel="label" optionValue="value" class="w-full md:w-60" />
            </div>
        </section>

        <Button type="submit" :label="t('admin.settings.save')" icon="pi pi-check" :loading="form.processing" />
    </form>
</template>
