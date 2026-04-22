<script setup lang="ts">
interface Props {
    color?: string;
    size?: number;
}
withDefaults(defineProps<Props>(), { color: 'currentColor', size: 6 });
</script>

<template>
    <span
        aria-hidden="true"
        :style="{
            display: 'inline-block',
            width: `${size}px`,
            height: `${size}px`,
            borderRadius: '50%',
            background: color,
            flexShrink: 0,
        }"
    />
</template>
