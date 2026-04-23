<script setup lang="ts">
/*
 * Command button. Variants: primary (accent bg), ghost (panel2 bg),
 * danger (danger fg on transparent, danger-soft hover). Sizes sm
 * (24px) and md (30px). Supports `loading`, `disabled`, and a
 * leading icon via the `icon` slot.
 */
import { computed } from 'vue';

type Variant = 'primary' | 'ghost' | 'danger';
type Size = 'sm' | 'md';

interface Props {
    variant?: Variant;
    size?: Size;
    type?: 'button' | 'submit' | 'reset';
    disabled?: boolean;
    loading?: boolean;
    fullWidth?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'primary',
    size: 'md',
    type: 'button',
    disabled: false,
    loading: false,
    fullWidth: false,
});

const sizeStyle = computed(() => {
    if (props.size === 'sm') {
        return { padding: '4px 9px', fontSize: '11.5px', minHeight: '24px' };
    }
    return { padding: '6px 12px', fontSize: '12.5px', minHeight: '30px' };
});

const variantStyle = computed(() => {
    if (props.variant === 'primary') {
        return {
            background: 'var(--accent)',
            color: '#fff',
            border: '1px solid transparent',
        };
    }
    if (props.variant === 'danger') {
        return {
            background: 'rgba(255, 138, 138, 0.10)',
            color: 'var(--danger)',
            border: '1px solid rgba(255, 138, 138, 0.33)',
        };
    }
    return {
        background: 'var(--panel2)',
        color: 'var(--fg)',
        border: '1px solid var(--border)',
    };
});

const isDisabled = computed(() => props.disabled || props.loading);
</script>

<template>
    <button
        :type="type"
        :disabled="isDisabled"
        class="cmd-btn"
        :class="[`cmd-btn-${variant}`]"
        :style="{
            ...sizeStyle,
            ...variantStyle,
            width: fullWidth ? '100%' : undefined,
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center',
            gap: '6px',
            borderRadius: 'var(--radius-control)',
            fontWeight: 500,
            fontFamily: 'inherit',
            lineHeight: 1,
            cursor: isDisabled ? 'not-allowed' : 'pointer',
            opacity: isDisabled ? 0.55 : 1,
            transition: 'background 0.12s, border-color 0.12s, opacity 0.12s',
            whiteSpace: 'nowrap',
        }"
    >
        <span
            v-if="loading"
            aria-hidden="true"
            :style="{
                width: '11px',
                height: '11px',
                borderRadius: '50%',
                border: '2px solid currentColor',
                borderTopColor: 'transparent',
                display: 'inline-block',
                animation: 'cmdSpin 0.7s linear infinite',
            }"
        />
        <slot v-else name="icon" />
        <slot />
    </button>
</template>

<style scoped>
.cmd-btn-primary:not(:disabled):hover {
    filter: brightness(1.08);
}
.cmd-btn-ghost:not(:disabled):hover {
    background: var(--panel) !important;
    border-color: var(--accent-border) !important;
}
.cmd-btn-danger:not(:disabled):hover {
    background: rgba(255, 138, 138, 0.18) !important;
}
.cmd-btn:focus-visible {
    outline: none;
    box-shadow: 0 0 0 2px var(--bg), 0 0 0 4px var(--accent-border);
}
@keyframes cmdSpin {
    to { transform: rotate(360deg); }
}
</style>
