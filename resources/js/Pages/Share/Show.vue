<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ImageLightbox from '@/Components/Files/ImageLightbox.vue';
import type { LightboxItem } from '@/types/lightbox';

interface Item {
    id: number;
    name: string;
    type: 'folder' | 'file';
    mime_type: string | null;
    size: number;
    is_image: boolean;
    thumbnail_url: string | null;
    preview_url: string | null;
    original_url: string | null;
    available_sizes: Record<string, { url: string; width: number; height: number }> | null;
}

const props = defineProps<{
    item: Item;
    children: Item[];
    share: { token: string; expires_at: string };
}>();

const { t } = useI18n();
const lightboxIndex = ref<number | null>(null);

const viewables = computed(() => props.item.type === 'folder' ? props.children : [props.item]);
const imageItems = computed(() => viewables.value.filter((i) => i.type === 'file' && i.is_image));

const lightboxItems = computed<LightboxItem[]>(() =>
    imageItems.value.map((i) => ({
        id: i.id,
        src: i.preview_url ?? i.original_url ?? '',
        zoomSrc: i.available_sizes?.large?.url ?? i.available_sizes?.xlarge?.url ?? i.original_url ?? undefined,
        originalSrc: i.original_url ?? undefined,
        alt: i.name,
        canZoom: true,
    })),
);

function download(item: Item) {
    // Folders have no binary to download — ignore instead of 404-ing.
    if (item.type === 'folder') return;
    window.location.href = `/share/${props.share.token}/files/${item.id}/download`;
}

function openItem(item: Item) {
    if (item.type === 'folder') return;
    if (item.is_image) {
        const idx = imageItems.value.findIndex((i) => i.id === item.id);
        if (idx >= 0) lightboxIndex.value = idx;
    } else {
        download(item);
    }
}

function iconFor(item: Item): string {
    if (item.type === 'folder') return 'pi-folder';
    if (item.is_image) return 'pi-image';
    if (item.mime_type === 'application/pdf') return 'pi-file-pdf';
    if (item.mime_type?.startsWith('video/')) return 'pi-video';
    return 'pi-file';
}

function formatBytes(n: number): string {
    if (!n) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0, v = n;
    while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
    return `${v.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

const expiresLabel = computed(() => new Date(props.share.expires_at).toLocaleString());
</script>

<template>
    <Head :title="props.item.name" />

    <div class="min-h-screen bg-slate-50 dark:bg-dark-950">
        <header class="border-b border-slate-200 bg-white px-4 py-4 dark:border-dark-800 dark:bg-dark-900">
            <div class="mx-auto flex max-w-5xl items-center justify-between">
                <h1 class="flex items-center gap-2 text-lg font-semibold text-slate-800 dark:text-slate-100">
                    <i :class="`pi ${iconFor(props.item)} text-indigo-500`" />
                    {{ props.item.name }}
                </h1>
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    {{ t('share.expires', { when: expiresLabel }) }}
                </span>
            </div>
        </header>

        <main class="mx-auto max-w-5xl p-4 sm:p-6">
            <!-- Single-file view -->
            <section v-if="props.item.type === 'file'" class="rounded-lg border border-slate-200 bg-white p-6 dark:border-dark-700 dark:bg-dark-900">
                <div v-if="props.item.is_image" class="flex flex-col items-center gap-4">
                    <button type="button" class="cursor-zoom-in overflow-hidden rounded" @click="lightboxIndex = 0">
                        <img :src="props.item.preview_url ?? props.item.original_url ?? ''" :alt="props.item.name" class="max-h-[70vh] w-auto" />
                    </button>
                </div>
                <div v-else class="flex flex-col items-center gap-4 py-12">
                    <i :class="`pi ${iconFor(props.item)} text-6xl text-slate-400`" />
                    <p class="text-slate-600 dark:text-slate-300">{{ props.item.name }} · {{ formatBytes(props.item.size) }}</p>
                </div>
                <div class="mt-6 flex justify-center">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                        @click="download(props.item)"
                    >
                        <i class="pi pi-download" />
                        {{ t('share.download') }}
                    </button>
                </div>
            </section>

            <!-- Folder view -->
            <section v-else>
                <div v-if="props.children.length === 0" class="rounded-lg border border-dashed border-slate-300 bg-white py-24 text-center dark:border-dark-700 dark:bg-dark-900">
                    <i class="pi pi-folder-open text-4xl text-slate-400" />
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ t('share.empty_folder') }}</p>
                </div>
                <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    <button
                        v-for="child in props.children"
                        :key="child.id"
                        type="button"
                        class="group overflow-hidden rounded-lg border border-slate-200 bg-white text-left transition hover:border-indigo-400 dark:border-dark-700 dark:bg-dark-900"
                        @click="openItem(child)"
                        @dblclick="download(child)"
                    >
                        <div class="flex aspect-square items-center justify-center overflow-hidden bg-slate-50 dark:bg-dark-800">
                            <img v-if="child.thumbnail_url" :src="child.thumbnail_url" :alt="child.name" class="h-full w-full object-cover" loading="lazy" />
                            <i v-else :class="`pi ${iconFor(child)} text-5xl text-slate-400`" />
                        </div>
                        <div class="flex items-center justify-between gap-2 px-2 py-1.5 text-xs">
                            <span class="truncate text-slate-700 dark:text-slate-200">{{ child.name }}</span>
                            <i class="pi pi-download text-slate-400 opacity-0 transition group-hover:opacity-100" @click.stop="download(child)" />
                        </div>
                    </button>
                </div>
            </section>
        </main>

        <ImageLightbox v-if="lightboxItems.length" v-model="lightboxIndex" :items="lightboxItems" />
    </div>
</template>
