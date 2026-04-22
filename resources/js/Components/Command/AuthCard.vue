<script setup lang="ts">
/*
 * Shared auth card shell. Provides a panel-backed box, a mono eyebrow, a
 * large title, a dim subtitle, and a slot for the form.
 */
interface Props {
    eyebrow?: string;
    title: string;
    subtitle?: string;
}
defineProps<Props>();
</script>

<template>
    <div
        :style="{
            background: 'var(--panel)',
            border: '1px solid var(--border)',
            borderRadius: '8px',
            padding: '28px 28px 24px',
            boxShadow: '0 8px 32px rgba(0,0,0,0.25)',
        }"
    >
        <div
            v-if="eyebrow"
            class="cmd-mono cmd-uc"
            :style="{ fontSize: '9.5px', color: 'var(--fg-mute)', marginBottom: '14px', fontWeight: 500, letterSpacing: '0.08em' }"
        >{{ eyebrow }}</div>
        <h1
            :style="{
                margin: 0,
                fontSize: '22px',
                fontWeight: 600,
                letterSpacing: '-0.02em',
                color: 'var(--fg)',
            }"
        >{{ title }}</h1>
        <p
            v-if="subtitle"
            :style="{ margin: '6px 0 24px', fontSize: '12.5px', color: 'var(--fg-dim)', lineHeight: 1.5 }"
        >{{ subtitle }}</p>
        <div v-else :style="{ height: '24px' }" />
        <slot />
    </div>
</template>
