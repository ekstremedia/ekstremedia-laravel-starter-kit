<script setup lang="ts">
/*
 * Command-styled native `<select>` wrapper. Same visual shape as
 * Field (panel2 background, accent border on focus). Takes an
 * array of `{ value, label }` options.
 */
import { computed, useId } from 'vue';

interface Option {
    value: string | number;
    label: string;
    disabled?: boolean;
}

interface Props {
    modelValue: string | number | null;
    options: Option[];
    label?: string;
    id?: string;
    disabled?: boolean;
    error?: string | null;
    placeholder?: string;
}

const props = withDefaults(defineProps<Props>(), {
    label: '',
    id: undefined,
    disabled: false,
    error: '',
    placeholder: '',
});

const emit = defineEmits<{ 'update:modelValue': [value: string | number] }>();

const autoId = useId();
const selectId = computed(() => props.id ?? `cmd-select-${autoId}`);

const value = computed({
    get: () => props.modelValue ?? '',
    set: (v) => {
        // Infer numeric-ness from the options rather than the current
        // modelValue so `null` initial state still coerces correctly.
        const firstOption = props.options[0];
        const isNumeric = firstOption ? typeof firstOption.value === 'number' : typeof props.modelValue === 'number';
        const parsed = isNumeric ? Number(v) : v;
        emit('update:modelValue', parsed as string | number);
    },
});

function onFocus(e: FocusEvent) {
    if (!props.error && e.target instanceof HTMLElement) {
        e.target.style.borderColor = 'var(--accent)';
    }
}
function onBlur(e: FocusEvent) {
    if (!props.error && e.target instanceof HTMLElement) {
        e.target.style.borderColor = 'var(--border)';
    }
}
</script>

<template>
    <div>
        <label
            v-if="label"
            :for="selectId"
            class="cmd-mono cmd-uc"
            :style="{ display: 'block', fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '6px', letterSpacing: '0.06em', fontWeight: 500 }"
        >{{ label }}</label>
        <select
            :id="selectId"
            v-model="value"
            :disabled="disabled"
            :aria-invalid="!!error"
            :style="{
                width: '100%',
                background: 'var(--panel2)',
                border: `1px solid ${error ? 'var(--danger)' : 'var(--border)'}`,
                borderRadius: '5px',
                padding: '8px 10px',
                color: 'var(--fg)',
                fontSize: '13px',
                outline: 'none',
                fontFamily: 'inherit',
                transition: 'border-color 0.12s',
                cursor: disabled ? 'not-allowed' : 'pointer',
            }"
            @focus="onFocus"
            @blur="onBlur"
        >
            <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
            <option
                v-for="opt in options"
                :key="opt.value"
                :value="opt.value"
                :disabled="opt.disabled"
            >{{ opt.label }}</option>
        </select>
        <div
            v-if="error"
            :style="{ color: 'var(--danger)', fontSize: '11px', marginTop: '4px' }"
        >{{ error }}</div>
    </div>
</template>
