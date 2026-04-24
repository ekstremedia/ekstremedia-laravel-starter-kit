<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * Usage meter that renders identically on Private and Shared Files.
 * `quotaBytes = null` means unlimited; the bar is rendered but its
 * percent stays at 0 in that case. `quotaUnlimited` is an optional hint
 * (some callers prefer to distinguish "no cap configured" from "explicit
 * unlimited"); when true we show the word "Unlimited" instead of a
 * value.
 */
const props = defineProps<{
    usedBytes: number;
    quotaBytes: number | null;
    quotaUnlimited?: boolean;
    // Optional label override — defaults to files.used_of.
    label?: string;
}>();

const { t } = useI18n();

function humanBytes(n: number | null | undefined): string {
    if (n == null || n < 0) return '—';
    if (n === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0; let v = n;
    while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
    return `${v.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

const percent = computed(() => {
    if (props.quotaBytes == null || props.quotaBytes <= 0) return 0;
    return Math.min(100, (props.usedBytes / props.quotaBytes) * 100);
});

const quotaDisplay = computed(() => {
    if (props.quotaUnlimited || props.quotaBytes === null) return t('files.unlimited');
    // 0 is the "blocked" sentinel — show Disabled instead of "0 B" so
    // admins configuring a hard block get the right UX instead of a
    // misleading "used of 0 B" label.
    if (props.quotaBytes === 0) return t('files.disabled');
    return humanBytes(props.quotaBytes);
});

const isBlocked = computed(() => props.quotaBytes === 0);
</script>

<template>
    <div :style="{ marginBottom: '14px', background: 'var(--panel2)', border: '1px solid var(--border)', borderRadius: '6px', padding: '10px 14px' }">
        <div :style="{ display: 'flex', justifyContent: 'space-between', fontSize: '11.5px', color: 'var(--fg-dim)', marginBottom: '6px' }">
            <span>{{ isBlocked ? quotaDisplay : (label ?? t('files.used_of', { used: humanBytes(usedBytes), quota: quotaDisplay })) }}</span>
            <span v-if="quotaBytes !== null && !quotaUnlimited && !isBlocked">{{ percent.toFixed(1) }}%</span>
        </div>
        <div v-if="!isBlocked" :style="{ height: '4px', background: 'var(--border)', borderRadius: '2px', overflow: 'hidden' }">
            <div
                :style="{
                    height: '100%',
                    width: `${percent}%`,
                    background: percent >= 95 ? 'var(--danger)' : percent >= 80 ? 'var(--warning)' : 'var(--accent)',
                    transition: 'width 0.2s',
                }"
            />
        </div>
    </div>
</template>
