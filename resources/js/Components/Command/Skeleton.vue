<script setup lang="ts">
interface Props {
    width?: string | number;
    height?: string | number;
    radius?: string | number;
}
withDefaults(defineProps<Props>(), { width: '100%', height: 12, radius: 4 });

function asSize(v: string | number) {
    return typeof v === 'number' ? `${v}px` : v;
}
</script>

<template>
    <span
        class="cmd-skeleton"
        :style="{
            display: 'inline-block',
            width: asSize(width),
            height: asSize(height),
            borderRadius: asSize(radius),
        }"
    />
</template>
