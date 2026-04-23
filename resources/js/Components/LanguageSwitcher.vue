<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useLocale } from '@/composables/useLocale';
import Icon from '@/Components/Command/Icon.vue';

const { currentLocale, setLocale, locales } = useLocale();

const open = ref(false);
const current = computed(() => locales.find((l) => l.code === currentLocale.value) ?? locales[0]);

function toggle() {
    open.value = !open.value;
}

function pick(code: string) {
    setLocale(code);
    open.value = false;
}

function onClickOutside(e: MouseEvent) {
    const el = e.target as HTMLElement;
    if (!el.closest('[data-lang-switcher]')) open.value = false;
}

onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>

<template>
    <div :style="{ position: 'relative' }" data-lang-switcher>
        <button
            type="button"
            class="cmd-lang-trigger"
            :title="current.name"
            :aria-label="`Current language: ${current.name}`"
            @click="toggle"
            :style="{
                display: 'inline-flex',
                alignItems: 'center',
                gap: '6px',
                padding: '5px 8px',
                borderRadius: '5px',
                background: 'transparent',
                border: '1px solid transparent',
                color: 'var(--fg-dim)',
                cursor: 'pointer',
                fontFamily: 'inherit',
                transition: 'background 0.12s, border-color 0.12s',
            }"
        >
            <span :style="{ fontSize: '16px', lineHeight: 1 }">{{ current.flag }}</span>
            <Icon name="chevD" :size="10" :style="{ color: 'var(--fg-mute)' }" />
        </button>

        <Transition
            enter-active-class="cmd-lang-enter"
            leave-active-class="cmd-lang-leave"
        >
            <div
                v-if="open"
                :style="{
                    position: 'absolute',
                    right: 0,
                    marginTop: '6px',
                    width: '170px',
                    background: 'var(--panel)',
                    border: '1px solid var(--border)',
                    borderRadius: '6px',
                    boxShadow: 'var(--shadow-palette)',
                    padding: '4px',
                    zIndex: 60,
                }"
            >
                <button
                    v-for="loc in locales"
                    :key="loc.code"
                    type="button"
                    @click="pick(loc.code)"
                    :style="{
                        width: '100%',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '8px',
                        padding: '6px 8px',
                        borderRadius: '4px',
                        background: loc.code === currentLocale ? 'var(--accent-soft)' : 'transparent',
                        border: 'none',
                        color: loc.code === currentLocale ? 'var(--fg)' : 'var(--fg-dim)',
                        fontWeight: loc.code === currentLocale ? 500 : 400,
                        fontSize: '12.5px',
                        cursor: 'pointer',
                        fontFamily: 'inherit',
                        textAlign: 'left',
                    }"
                    class="cmd-lang-item"
                >
                    <span :style="{ fontSize: '15px', lineHeight: 1 }">{{ loc.flag }}</span>
                    <span>{{ loc.name }}</span>
                    <Icon
                        v-if="loc.code === currentLocale"
                        name="check"
                        :size="11"
                        :style="{ color: 'var(--accent)', marginLeft: 'auto' }"
                    />
                </button>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.cmd-lang-trigger:hover {
    background: var(--panel2);
    border-color: var(--border);
}
.cmd-lang-item:hover {
    background: var(--accent-soft) !important;
    color: var(--fg) !important;
}
.cmd-lang-enter {
    animation: cmdFadeIn 0.12s ease-out;
}
.cmd-lang-leave {
    opacity: 0;
    transition: opacity 0.1s;
}
</style>
