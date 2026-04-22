<script setup lang="ts">
/* Bottom-right toast stack. Mono pill with leading dot indicator. Auto-
 * dismiss is handled inside useCommandToasts(); this component just renders. */
import { useCommandToasts } from '@/composables/useCommandToasts';
import Dot from './Dot.vue';

const { toasts } = useCommandToasts();

function toneColor(tone: string): string {
    if (tone === 'danger') return 'var(--danger)';
    if (tone === 'success') return 'var(--success)';
    if (tone === 'warning') return 'var(--warning)';
    return 'var(--accent)';
}

function toneBorder(tone: string): string {
    if (tone === 'danger') return '#ff8a8a44';
    if (tone === 'success') return '#5ee59a44';
    if (tone === 'warning') return '#fbbf2444';
    return 'var(--border)';
}
</script>

<template>
    <div
        :style="{
            position: 'fixed',
            bottom: '16px',
            right: '16px',
            display: 'flex',
            flexDirection: 'column',
            gap: '6px',
            zIndex: 120,
            pointerEvents: 'none',
        }"
    >
        <div
            v-for="t in toasts"
            :key="t.id"
            class="cmd-mono"
            :style="{
                background: 'var(--panel2)',
                border: `1px solid ${toneBorder(t.tone)}`,
                padding: '8px 14px',
                fontSize: '11.5px',
                color: 'var(--fg)',
                display: 'flex',
                alignItems: 'center',
                gap: '8px',
                borderRadius: '5px',
                animation: 'cmdToastIn 0.18s ease-out',
                boxShadow: 'var(--shadow-toast)',
                pointerEvents: 'auto',
            }"
        >
            <Dot :color="toneColor(t.tone)" :size="5" />
            <span>{{ t.msg }}</span>
        </div>
    </div>
</template>
