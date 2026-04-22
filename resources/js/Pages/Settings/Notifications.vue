<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/CommandLayout.vue';
import Toggle from '@/Components/Command/Toggle.vue';
import Icon, { type IconName } from '@/Components/Command/Icon.vue';

const props = defineProps<{
    preferences: {
        notification_email_immediate: boolean;
        notification_digest: string;
        notification_chat_messages: boolean;
        notification_account_updates: boolean;
        notification_system_alerts: boolean;
        notification_storage_alerts: boolean;
    };
}>();

const { t } = useI18n();

const form = useForm({
    notification_email_immediate: props.preferences.notification_email_immediate,
    notification_digest: props.preferences.notification_digest,
    notification_chat_messages: props.preferences.notification_chat_messages,
    notification_account_updates: props.preferences.notification_account_updates,
    notification_system_alerts: props.preferences.notification_system_alerts,
    notification_storage_alerts: props.preferences.notification_storage_alerts,
});

const digestOptions = [
    { value: 'none', label: t('notifications.settings.digest_none') },
    { value: 'daily', label: t('notifications.settings.digest_daily') },
    { value: 'weekly', label: t('notifications.settings.digest_weekly') },
];

const perType: { key: 'notification_chat_messages' | 'notification_account_updates' | 'notification_system_alerts' | 'notification_storage_alerts'; icon: IconName; label: string }[] = [
    { key: 'notification_chat_messages', icon: 'mail', label: t('notifications.settings.chat_messages') },
    { key: 'notification_account_updates', icon: 'user', label: t('notifications.settings.account_updates') },
    { key: 'notification_system_alerts', icon: 'bell', label: t('notifications.settings.system_alerts') },
    { key: 'notification_storage_alerts', icon: 'disk', label: t('notifications.settings.storage_alerts') },
];

function submit() {
    form.put('/settings/notifications', { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('notifications.settings.title')" />

        <section :style="{ maxWidth: '780px', margin: '0 auto', padding: '32px 16px', display: 'flex', flexDirection: 'column', gap: '20px' }">
            <header>
                <h1 :style="{ margin: 0, fontSize: '20px', fontWeight: 600, letterSpacing: '-0.01em', color: 'var(--fg)' }">
                    {{ t('notifications.settings.title') }}
                </h1>
                <p
                    class="cmd-mono"
                    :style="{ marginTop: '4px', fontSize: '11.5px', color: 'var(--fg-mute)' }"
                >{{ t('notifications.settings.desc') }}</p>
            </header>

            <form @submit.prevent="submit" :style="{ display: 'flex', flexDirection: 'column', gap: '16px' }">
                <!-- Email delivery -->
                <div class="cmd-card" :style="{ padding: '20px', display: 'flex', flexDirection: 'column', gap: '16px' }">
                    <h2 :style="{ margin: 0, fontSize: '13px', fontWeight: 600, color: 'var(--fg)' }">
                        {{ t('notifications.settings.email_delivery') }}
                    </h2>

                    <label :style="{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '16px', cursor: 'pointer' }">
                        <div :style="{ minWidth: 0 }">
                            <div :style="{ fontSize: '12.5px', fontWeight: 500, color: 'var(--fg)' }">
                                {{ t('notifications.settings.email_on_new') }}
                            </div>
                            <p :style="{ fontSize: '11px', color: 'var(--fg-mute)', margin: '3px 0 0' }">
                                {{ t('notifications.settings.email_on_new_desc') }}
                            </p>
                        </div>
                        <Toggle v-model="form.notification_email_immediate" :label="t('notifications.settings.email_on_new')" />
                    </label>

                    <div>
                        <label
                            for="notification-digest"
                            class="cmd-mono cmd-uc"
                            :style="{ display: 'block', fontSize: '10px', fontWeight: 500, letterSpacing: '0.06em', color: 'var(--fg-mute)', marginBottom: '6px' }"
                        >{{ t('notifications.settings.digest') }}</label>
                        <div :style="{ display: 'flex', gap: '6px', flexWrap: 'wrap' }">
                            <button
                                v-for="opt in digestOptions"
                                :key="opt.value"
                                type="button"
                                @click="form.notification_digest = opt.value"
                                :style="{
                                    padding: '5px 11px',
                                    fontSize: '11.5px',
                                    borderRadius: '5px',
                                    cursor: 'pointer',
                                    fontFamily: 'inherit',
                                    background: form.notification_digest === opt.value ? 'var(--accent-soft)' : 'transparent',
                                    border: `1px solid ${form.notification_digest === opt.value ? 'var(--accent-border)' : 'var(--border)'}`,
                                    color: form.notification_digest === opt.value ? 'var(--fg)' : 'var(--fg-dim)',
                                }"
                            >{{ opt.label }}</button>
                        </div>
                    </div>
                </div>

                <!-- Per-type toggles -->
                <div class="cmd-card" :style="{ padding: '20px', display: 'flex', flexDirection: 'column', gap: '14px' }">
                    <div>
                        <h2 :style="{ margin: 0, fontSize: '13px', fontWeight: 600, color: 'var(--fg)' }">
                            {{ t('notifications.settings.per_type_title') }}
                        </h2>
                        <p :style="{ fontSize: '11px', color: 'var(--fg-mute)', margin: '3px 0 0' }">
                            {{ t('notifications.settings.per_type_desc') }}
                        </p>
                    </div>

                    <label
                        v-for="row in perType"
                        :key="row.key"
                        :style="{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '16px', cursor: 'pointer' }"
                    >
                        <div :style="{ display: 'flex', alignItems: 'center', gap: '10px', minWidth: 0 }">
                            <Icon :name="row.icon" :size="13" :style="{ color: 'var(--accent)' }" />
                            <span :style="{ fontSize: '12.5px', color: 'var(--fg)' }">{{ row.label }}</span>
                        </div>
                        <Toggle v-model="form[row.key]" :label="row.label" />
                    </label>
                </div>

                <div :style="{ display: 'flex', justifyContent: 'flex-end' }">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        :style="{
                            background: 'var(--accent)',
                            color: '#fff',
                            border: 'none',
                            padding: '7px 14px',
                            borderRadius: '5px',
                            fontSize: '12px',
                            fontWeight: 500,
                            cursor: form.processing ? 'not-allowed' : 'pointer',
                            opacity: form.processing ? 0.6 : 1,
                            fontFamily: 'inherit',
                        }"
                    >{{ t('notifications.settings.save') }}</button>
                </div>
            </form>
        </section>
    </AppLayout>
</template>
