<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    modelValue: boolean;
    src: string | null;
    poster?: string | null;
    title?: string | null;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();

const { t } = useI18n();
const videoRef = ref<HTMLVideoElement | null>(null);

function close() {
    emit('update:modelValue', false);
}

function onKey(e: KeyboardEvent) {
    if (!props.modelValue) return;
    if (e.key === 'Escape') close();
    if (e.key === ' ') {
        e.preventDefault();
        const v = videoRef.value;
        if (!v) return;
        v.paused ? v.play() : v.pause();
    }
}

onMounted(() => document.addEventListener('keydown', onKey));
onUnmounted(() => document.removeEventListener('keydown', onKey));

// Auto-play when opened. Muted first so most browsers honor it, then the
// user can unmute from the native controls.
watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;
        setTimeout(() => {
            const v = videoRef.value;
            if (!v) return;
            // Browsers block unmuted autoplay without user gesture; muting
            // lets the video actually start. The native controls let the user
            // unmute immediately if they want audio.
            v.muted = true;
            v.play().catch(() => undefined);
        }, 50);
    },
);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="modelValue && src"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
                role="dialog"
                aria-modal="true"
                :aria-label="title ?? t('common.close')"
                @click.self="close"
            >
                <div class="relative w-full max-w-5xl">
                    <!-- Close + title row -->
                    <div class="mb-2 flex items-center justify-between gap-2 text-white">
                        <div class="flex items-center gap-2 truncate text-sm">
                            <i class="pi pi-video text-indigo-300" />
                            <span class="truncate">{{ title ?? '' }}</span>
                        </div>
                        <button
                            type="button"
                            class="rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
                            :title="t('common.close')"
                            :aria-label="t('common.close')"
                            @click="close"
                        >
                            <i class="pi pi-times" />
                        </button>
                    </div>

                    <video
                        ref="videoRef"
                        :src="src ?? undefined"
                        :poster="poster ?? undefined"
                        controls
                        muted
                        preload="metadata"
                        playsinline
                        class="max-h-[80vh] w-full rounded-lg bg-black"
                    />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
