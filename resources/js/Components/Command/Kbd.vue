<script setup lang="ts">
/* Mono pill for keyboard hints (⌘K, G D, ESC). */
</script>

<template>
    <kbd
        class="cmd-mono"
        :style="{
            display: 'inline-flex',
            alignItems: 'center',
            padding: '1px 5px',
            fontSize: '9.5px',
            lineHeight: 1.4,
            color: 'var(--fg-dim)',
            background: 'var(--panel2)',
            border: '1px solid var(--border)',
            borderRadius: '3px',
            fontWeight: 500,
        }"
    >
        <slot />
    </kbd>
</template>
