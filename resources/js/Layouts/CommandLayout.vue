<script setup lang="ts">
/*
 * CommandLayout — rail (52 px) + topbar (42 px) + scrollable content slot.
 * Hosts the command palette, tweaks panel, toast stack, and the global
 * keyboard layer (⌘K, G+H/D/U/A, ?, Esc).
 *
 * Replaces the old AdminLayout. Also usable from non-admin authenticated
 * pages (e.g. /home) so the rail + palette stay consistent across the app.
 */
import { computed, ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useFlashToast } from '@/composables/useFlashToast';
import { useTweaks } from '@/composables/useTweaks';
import { useCommandKeyboard } from '@/composables/useCommandKeyboard';
import { useUserChannel } from '@/composables/useUserChannel';
import { useUnreadCounts } from '@/composables/useUnreadCounts';
import type { PageProps } from '@/types';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import Rail from '@/Components/Command/Rail.vue';
import Topbar from '@/Components/Command/Topbar.vue';
import CommandPalette from '@/Components/Command/CommandPalette.vue';
import TweaksPanel from '@/Components/Command/TweaksPanel.vue';
import ToastStack from '@/Components/Command/ToastStack.vue';
import Icon from '@/Components/Command/Icon.vue';

// Initialise tweaks (idempotent — applies CSS vars + keeps .dark in sync).
useTweaks();
useFlashToast();

const { t } = useI18n();
const page = usePage<PageProps>();
const user = computed(() => page.props.auth?.user);
const isImpersonating = computed(() => user.value?.is_impersonating ?? false);
const announcement = computed(() => page.props.app_settings?.announcement ?? null);

const announcementBg: Record<string, string> = {
    info: '#0ea5e9',
    warn: 'var(--warning)',
    danger: 'var(--danger)',
    success: 'var(--success)',
};

function leaveImpersonation() {
    router.post('/impersonate/leave');
}

// Server-pushed notifications keep the topbar bell badge in sync.
const { incrementMessages, incrementNotifications } = useUnreadCounts();
useUserChannel((n) => {
    const isChat = typeof n.type === 'string' && n.type.endsWith('NewChatMessageNotification');
    if (isChat) incrementMessages(1);
    else incrementNotifications(1);
});

const paletteOpen = ref(false);
const tweaksOpen = ref(false);

useCommandKeyboard({
    onTogglePalette: () => { paletteOpen.value = !paletteOpen.value; },
    onClosePalette: () => { paletteOpen.value = false; tweaksOpen.value = false; },
});
</script>

<template>
    <div
        class="cmd-shell"
        :style="{
            display: 'flex',
            flexDirection: 'column',
            minHeight: '100vh',
            width: '100%',
            position: 'relative',
        }"
    >
        <Toast position="top-right" />
        <ConfirmDialog group="command" />
        <!-- Announcement banner (global, controlled from Appinnstillinger). -->
        <div
            v-if="announcement && announcement.text"
            :style="{
                padding: '7px 16px',
                fontSize: '11.5px',
                textAlign: 'center',
                color: '#0a0c12',
                background: announcementBg[announcement.severity] ?? announcementBg.info,
                fontWeight: 500,
            }"
        >{{ announcement.text }}</div>

        <!-- Impersonation banner — amber, with exit action on the right. -->
        <div
            v-if="isImpersonating"
            :style="{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: '12px',
                padding: '6px 16px',
                background: 'var(--warning)',
                color: '#0a0c12',
                fontSize: '11.5px',
                fontWeight: 500,
            }"
        >
            <div :style="{ display: 'flex', alignItems: 'center', gap: '8px', overflow: 'hidden' }">
                <Icon name="user" :size="13" />
                <span :style="{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }">
                    {{ t('impersonation.banner') }} <strong>{{ user?.email }}</strong>.
                </span>
            </div>
            <button
                type="button"
                @click="leaveImpersonation"
                :style="{
                    background: 'rgba(10,12,18,0.15)',
                    color: '#0a0c12',
                    border: '1px solid rgba(10,12,18,0.2)',
                    padding: '3px 10px',
                    borderRadius: '4px',
                    fontSize: '11px',
                    fontWeight: 600,
                    cursor: 'pointer',
                    fontFamily: 'inherit',
                    flexShrink: 0,
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: '5px',
                }"
            >
                <Icon name="arrow" :size="11" />
                {{ t('impersonation.stop') }}
            </button>
        </div>

        <div :style="{ display: 'flex', flex: 1, minHeight: 0 }">
            <Rail />

        <div :style="{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }">
            <Topbar :on-open-palette="() => (paletteOpen = true)" />

            <main
                :style="{
                    flex: 1,
                    overflow: 'auto',
                    background: 'var(--bg)',
                    padding: 'var(--pad-page)',
                    animation: 'cmdFadeIn 0.2s ease-out',
                }"
            >
                <slot />
            </main>
            </div>
        </div>

        <!-- Tweaks trigger pinned bottom-left so it's easy to rediscover. -->
        <button
            type="button"
            class="cmd-tweaks-trigger"
            :class="{ 'is-open': tweaksOpen }"
            @click="tweaksOpen = !tweaksOpen"
            :title="tweaksOpen ? t('tweaks.close_title') : t('tweaks.open_title')"
            :aria-label="tweaksOpen ? t('tweaks.close_title') : t('tweaks.open_title')"
            :aria-expanded="tweaksOpen"
        >✦</button>

        <CommandPalette :open="paletteOpen" @close="paletteOpen = false" />
        <TweaksPanel :open="tweaksOpen" @close="tweaksOpen = false" />
        <ToastStack />
    </div>
</template>

<style scoped>
.cmd-tweaks-trigger {
    position: fixed;
    bottom: 16px;
    left: calc(var(--rail-w) + 14px);
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: var(--panel2);
    border: 1px solid var(--border);
    color: var(--fg-dim);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 105;
    padding: 0;
    font-size: 13px;
    opacity: 0.65;
    transition: opacity 0.12s, background 0.12s, border-color 0.12s;
}
.cmd-tweaks-trigger:hover { opacity: 1; }
.cmd-tweaks-trigger.is-open {
    background: var(--accent-soft);
    border-color: var(--accent-border);
    opacity: 1;
}
</style>
