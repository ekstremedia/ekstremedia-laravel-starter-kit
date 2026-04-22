<script setup lang="ts">
/* 32×18 pill toggle. Thumb is 14×14 white, translates 14px when on.
 * Background is `--accent` when on, `--panel2` when off. */
interface Props {
    modelValue: boolean;
    disabled?: boolean;
    label?: string;
}
const props = withDefaults(defineProps<Props>(), { disabled: false, label: undefined });
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();

function toggle() {
    if (props.disabled) return;
    emit('update:modelValue', !props.modelValue);
}
</script>

<template>
    <button
        type="button"
        role="switch"
        class="cmd-toggle"
        :aria-checked="modelValue"
        :aria-label="label || undefined"
        :disabled="disabled"
        @click="toggle"
        :style="{
            position: 'relative',
            width: '32px',
            height: '18px',
            borderRadius: '9999px',
            background: modelValue ? 'var(--accent)' : 'var(--panel2)',
            border: `1px solid ${modelValue ? 'var(--accent-border)' : 'var(--border)'}`,
            transition: 'background 0.12s, border-color 0.12s, box-shadow 0.12s',
            cursor: disabled ? 'not-allowed' : 'pointer',
            opacity: disabled ? 0.5 : 1,
            padding: 0,
            flexShrink: 0,
        }"
    >
        <span
            :style="{
                position: 'absolute',
                top: '1px',
                left: '1px',
                width: '14px',
                height: '14px',
                borderRadius: '50%',
                background: '#fff',
                transition: 'transform 0.12s',
                transform: modelValue ? 'translateX(14px)' : 'translateX(0)',
                boxShadow: '0 1px 2px rgba(0,0,0,0.3)',
            }"
        />
    </button>
</template>

<style scoped>
.cmd-toggle:focus-visible {
    outline: none;
    box-shadow: 0 0 0 2px var(--bg), 0 0 0 4px var(--accent-border);
}
</style>
