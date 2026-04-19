<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ref, onMounted } from 'vue';
import { gsap } from 'gsap';
import ToggleSwitch from 'primevue/toggleswitch';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps<{
    preferences: {
        notification_email_immediate: boolean;
        notification_digest: string;
        notification_chat_messages: boolean;
        notification_account_updates: boolean;
        notification_system_alerts: boolean;
    };
}>();

const { t } = useI18n();

const form = useForm({
    notification_email_immediate: props.preferences.notification_email_immediate,
    notification_digest: props.preferences.notification_digest,
    notification_chat_messages: props.preferences.notification_chat_messages,
    notification_account_updates: props.preferences.notification_account_updates,
    notification_system_alerts: props.preferences.notification_system_alerts,
});

const sectionsRef = ref<HTMLElement>();

onMounted(() => {
    if (!sectionsRef.value) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    gsap.from(sectionsRef.value.children, {
        y: 20,
        opacity: 0,
        duration: 0.4,
        stagger: 0.1,
        ease: 'power2.out',
        delay: 0.1,
    });
});

function submit() {
    // Flash success from the server is surfaced by useFlashToast globally —
    // no local toast.add here, to avoid duplicates.
    form.put('/settings/notifications', {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('notifications.settings.title')" />

        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <h1 class="text-2xl font-bold mb-2">{{ t('notifications.settings.title') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">{{ t('notifications.settings.desc') }}</p>

            <form @submit.prevent="submit" ref="sectionsRef" class="space-y-6">
                <!-- Email delivery -->
                <div class="bg-white dark:bg-dark-900 rounded-2xl border border-gray-200 dark:border-dark-700 p-6 space-y-5">
                    <h2 class="text-lg font-semibold">{{ t('notifications.settings.email_delivery') }}</h2>

                    <!-- Immediate emails -->
                    <label class="flex items-start justify-between gap-3 cursor-pointer">
                        <div>
                            <span class="text-sm font-medium">{{ t('notifications.settings.email_on_new') }}</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ t('notifications.settings.email_on_new_desc') }}</p>
                        </div>
                        <ToggleSwitch v-model="form.notification_email_immediate" />
                    </label>

                    <!-- Digest frequency -->
                    <div>
                        <label for="notification-digest" class="text-sm font-medium block mb-2">{{ t('notifications.settings.digest') }}</label>
                        <select
                            id="notification-digest"
                            v-model="form.notification_digest"
                            class="w-full sm:w-64 rounded-xl border border-gray-300 dark:border-dark-600 bg-white dark:bg-dark-800 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent cursor-pointer"
                        >
                            <option value="none">{{ t('notifications.settings.digest_none') }}</option>
                            <option value="daily">{{ t('notifications.settings.digest_daily') }}</option>
                            <option value="weekly">{{ t('notifications.settings.digest_weekly') }}</option>
                        </select>
                    </div>
                </div>

                <!-- Per-type toggles -->
                <div class="bg-white dark:bg-dark-900 rounded-2xl border border-gray-200 dark:border-dark-700 p-6 space-y-4">
                    <h2 class="text-lg font-semibold">{{ t('notifications.settings.per_type_title') }}</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('notifications.settings.per_type_desc') }}</p>

                    <label class="flex items-center justify-between gap-3 cursor-pointer">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-comments text-sm text-indigo-500"></i>
                            <span class="text-sm">{{ t('notifications.settings.chat_messages') }}</span>
                        </div>
                        <ToggleSwitch v-model="form.notification_chat_messages" />
                    </label>

                    <label class="flex items-center justify-between gap-3 cursor-pointer">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-user text-sm text-indigo-500"></i>
                            <span class="text-sm">{{ t('notifications.settings.account_updates') }}</span>
                        </div>
                        <ToggleSwitch v-model="form.notification_account_updates" />
                    </label>

                    <label class="flex items-center justify-between gap-3 cursor-pointer">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-bell text-sm text-indigo-500"></i>
                            <span class="text-sm">{{ t('notifications.settings.system_alerts') }}</span>
                        </div>
                        <ToggleSwitch v-model="form.notification_system_alerts" />
                    </label>
                </div>

                <!-- Save button -->
                <div class="flex justify-end">
                    <PrimaryButton :disabled="form.processing">
                        {{ t('notifications.settings.save') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
