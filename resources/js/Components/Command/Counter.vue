<script setup lang="ts">
/* Animates 0 → `to` over `duration` ms with cubic ease-out. 600 ms by
 * default to match the handoff. Re-runs when `to` changes. */
import { ref, watch, onBeforeUnmount } from 'vue';

interface Props {
    to: number;
    decimals?: number;
    duration?: number;
}

const props = withDefaults(defineProps<Props>(), { decimals: 0, duration: 600 });
const v = ref(0);
let raf: number | null = null;

function animate() {
    if (raf !== null) cancelAnimationFrame(raf);
    const target = props.to;
    if (!Number.isFinite(target)) {
        v.value = 0;
        return;
    }
    const start = performance.now();
    const tick = (now: number) => {
        const p = Math.min(1, (now - start) / props.duration);
        const eased = 1 - Math.pow(1 - p, 3);
        v.value = target * eased;
        if (p < 1) raf = requestAnimationFrame(tick);
    };
    raf = requestAnimationFrame(tick);
}

watch(() => props.to, animate, { immediate: true });

onBeforeUnmount(() => {
    if (raf !== null) cancelAnimationFrame(raf);
});
</script>

<template>
    <span>{{ v.toFixed(decimals) }}</span>
</template>
