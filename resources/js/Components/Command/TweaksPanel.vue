<script setup lang="ts">
/* Floating tweaks panel — bottom-left, 240 px. Theme / Accent / Density /
 * Hurtigtaster i rail. Hidden by default, shown via `?` shortcut or the
 * "Vis tweaks" command in the palette. Close with its × button. */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useTweaks } from '@/composables/useTweaks';
import type { CommandAccent, CommandDensity, CommandTheme } from '@/types';

interface Props { open: boolean }
defineProps<Props>();
const emit = defineEmits<{ close: [] }>();

const { t } = useI18n();
const { state, setTheme, setAccent, setDensity, setShowKbdHints } = useTweaks();

const themes = computed<{ id: CommandTheme; label: string }[]>(() => [
    { id: 'dark', label: t('tweaks.theme_dark') },
    { id: 'hc', label: t('tweaks.theme_hc') },
    { id: 'light', label: t('tweaks.theme_light') },
]);
const accents: { id: CommandAccent; color: string; label: string }[] = [
    { id: 'cobalt', color: '#4c6fff', label: 'Cobalt' },
    { id: 'emerald', color: '#10b981', label: 'Emerald' },
    { id: 'amber', color: '#f59e0b', label: 'Amber' },
    { id: 'violet', color: '#8b5cf6', label: 'Violet' },
];
const densities = computed<{ id: CommandDensity; label: string }[]>(() => [
    { id: 'compact', label: t('tweaks.density_compact') },
    { id: 'comfortable', label: t('tweaks.density_comfortable') },
    { id: 'relaxed', label: t('tweaks.density_relaxed') },
]);

function chipStyle(active: boolean) {
    return {
        padding: '3px 9px',
        fontSize: '11px',
        borderRadius: '4px',
        cursor: 'pointer',
        fontFamily: 'inherit',
        border: `1px solid ${active ? 'var(--accent-border)' : 'var(--border)'}`,
        background: active ? 'var(--accent-soft)' : 'transparent',
        color: active ? 'var(--fg)' : 'var(--fg-dim)',
    };
}
</script>

<template>
    <div
        v-if="open"
        :style="{
            position: 'fixed',
            bottom: '16px',
            left: '70px',
            width: '240px',
            zIndex: 110,
            background: 'var(--panel)',
            border: '1px solid var(--border)',
            borderRadius: '8px',
            padding: '14px',
            boxShadow: '0 8px 32px rgba(0,0,0,0.4)',
            animation: 'cmdFadeIn 0.12s ease-out',
        }"
    >
        <div :style="{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }">
            <span
                class="cmd-mono cmd-uc"
                :style="{ fontSize: '11px', color: 'var(--fg-mute)', fontWeight: 500 }"
            >{{ t('tweaks.title') }}</span>
            <button
                type="button"
                @click="emit('close')"
                :style="{ background: 'transparent', border: 'none', color: 'var(--fg-mute)', cursor: 'pointer', padding: '0 2px' }"
                :aria-label="t('common.close')"
            >×</button>
        </div>

        <div :style="{ marginBottom: '12px' }">
            <div class="cmd-mono" :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '6px' }">{{ t('tweaks.theme_heading') }}</div>
            <div :style="{ display: 'flex', gap: '4px', flexWrap: 'wrap' }">
                <button
                    v-for="th in themes"
                    :key="th.id"
                    type="button"
                    @click="setTheme(th.id)"
                    :style="chipStyle(state.theme === th.id)"
                >{{ th.label }}</button>
            </div>
        </div>

        <div :style="{ marginBottom: '12px' }">
            <div class="cmd-mono" :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '6px' }">{{ t('tweaks.accent_heading') }}</div>
            <div :style="{ display: 'flex', gap: '6px' }">
                <button
                    v-for="a in accents"
                    :key="a.id"
                    type="button"
                    @click="setAccent(a.id)"
                    :title="a.label"
                    :aria-label="t('tweaks.accent_select', { name: a.label })"
                    :aria-pressed="state.accent === a.id"
                    :style="{
                        width: '22px',
                        height: '22px',
                        borderRadius: '4px',
                        border: `2px solid ${state.accent === a.id ? 'var(--fg)' : 'transparent'}`,
                        background: a.color,
                        cursor: 'pointer',
                        padding: 0,
                    }"
                />
            </div>
        </div>

        <div :style="{ marginBottom: '12px' }">
            <div class="cmd-mono" :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '6px' }">{{ t('tweaks.density_heading') }}</div>
            <div :style="{ display: 'flex', gap: '4px', flexWrap: 'wrap' }">
                <button
                    v-for="d in densities"
                    :key="d.id"
                    type="button"
                    @click="setDensity(d.id)"
                    :style="chipStyle(state.density === d.id)"
                >{{ d.label }}</button>
            </div>
        </div>

        <div :style="{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }">
            <span class="cmd-mono" :style="{ fontSize: '10px', color: 'var(--fg-mute)' }">{{ t('tweaks.show_kbd_hints') }}</span>
            <button
                type="button"
                @click="setShowKbdHints(!state.showKbdHints)"
                :style="{
                    width: '32px',
                    height: '18px',
                    borderRadius: '18px',
                    padding: '2px',
                    border: 'none',
                    background: state.showKbdHints ? 'var(--accent)' : 'var(--border)',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                }"
                :aria-label="state.showKbdHints ? t('common.hide') : t('common.show')"
                :aria-pressed="state.showKbdHints"
            >
                <span
                    :style="{
                        width: '14px',
                        height: '14px',
                        borderRadius: '50%',
                        background: '#fff',
                        transform: state.showKbdHints ? 'translateX(14px)' : 'translateX(0)',
                        transition: 'transform 0.12s',
                    }"
                />
            </button>
        </div>
    </div>
</template>
