<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import UploadDialog from '@/Components/Files/UploadDialog.vue';
import ImageLightbox from '@/Components/Files/ImageLightbox.vue';
import VideoPlayer from '@/Components/Files/VideoPlayer.vue';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import { useCustomer } from '@/composables/useCustomer';
import type { LightboxItem } from '@/types/lightbox';
import type { PageProps } from '@/types';

interface FileItem {
    id: number;
    uuid: string;
    type: 'folder' | 'file';
    name: string;
    mime_type: string | null;
    size: number;
    parent_id: number | null;
    is_image: boolean;
    is_video?: boolean;
    video_processing?: boolean;
    video_ready?: boolean;
    thumbnail_url: string | null;
    preview_url: string | null;
    original_url: string | null;
    video_web_url?: string | null;
    video_poster_url?: string | null;
    available_sizes: Record<string, { url: string; width: number; height: number }> | null;
    created_at: string | null;
    updated_at: string | null;
}

interface Breadcrumb {
    id: number;
    name: string;
}

interface PageData {
    items: { data: FileItem[] };
    breadcrumbs: Breadcrumb[];
    current_folder: { id: number; name: string; uuid: string } | null;
    usage: { used_bytes: number; quota_bytes: number | null; percent: number };
    search: string | null;
}

const props = defineProps<PageData>();
const { t } = useI18n();
const { customerUrl } = useCustomer();
const page = usePage<PageProps>();

const viewMode = ref<'grid' | 'list'>((localStorage.getItem('files.viewMode') as 'grid' | 'list') || 'grid');
const uploadOpen = ref(false);
const lightboxIndex = ref<number | null>(null);
const videoOpen = ref(false);
const videoItem = ref<FileItem | null>(null);
const searchQuery = ref(props.search ?? '');
const renamingId = ref<number | null>(null);
const renameValue = ref('');
const draggingId = ref<number | null>(null);
const dragOverId = ref<number | null>(null);

const newFolderOpen = ref(false);
const newFolderName = ref('');
const confirm = useConfirm();
const toast = useToast();

const shareDialogFile = ref<FileItem | null>(null);
const sharePassword = ref('');
const shareHours = ref(24);
const shareCreating = ref(false);
const shareResultUrl = ref<string | null>(null);

function openShareDialog(item: FileItem) {
    shareDialogFile.value = item;
    sharePassword.value = '';
    shareHours.value = 24;
    shareResultUrl.value = null;
}

function shareErrorToast() {
    toast.add({
        severity: 'error',
        summary: t('files.share'),
        detail: t('files.share_failed'),
        life: 4000,
    });
}

async function createShare() {
    if (!shareDialogFile.value) return;
    shareCreating.value = true;
    try {
        const res = await fetch(customerUrl(`/files/${shareDialogFile.value.id}/shares`), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1] ?? ''),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                expires_in_hours: shareHours.value,
                password: sharePassword.value || null,
            }),
        });
        if (!res.ok) {
            shareErrorToast();
            return;
        }
        const data = await res.json();
        shareResultUrl.value = data.url;
        await navigator.clipboard?.writeText(data.url).catch(() => undefined);
    } catch {
        shareErrorToast();
    } finally {
        shareCreating.value = false;
    }
}

async function quickShare(item: FileItem) {
    if (item.type !== 'file') return openShareDialog(item);
    try {
        const res = await fetch(customerUrl(`/files/${item.id}/shares/signed`), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1] ?? ''),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ hours: 24 }),
        });
        if (!res.ok) {
            shareErrorToast();
            return;
        }
        const data = await res.json();
        await navigator.clipboard?.writeText(data.url).catch(() => undefined);
        shareResultUrl.value = data.url;
        shareDialogFile.value = item;
    } catch {
        shareErrorToast();
    }
}

const currentFolderId = computed(() => props.current_folder?.id ?? null);

const mergedItems = computed(() => props.items.data.map((i) => ({ ...i, ...(liveItems[i.id] ?? {}) })));
const imageItems = computed(() => mergedItems.value.filter((i) => i.type === 'file' && i.is_image));

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

function openItem(item: FileItem) {
    if (renamingId.value !== null) return;
    if (item.type === 'folder') {
        router.visit(customerUrl(`/files/${item.id}`));
        return;
    }
    if (item.is_image) {
        const idx = imageItems.value.findIndex((i) => i.id === item.id);
        if (idx >= 0) lightboxIndex.value = idx;
        return;
    }
    if (item.is_video && item.video_ready && item.video_web_url) {
        videoItem.value = item;
        videoOpen.value = true;
        return;
    }
    if (item.is_video && item.video_processing) {
        // Soft nudge — processing spinner already tells the story.
        return;
    }
    window.location.href = customerUrl(`/files/${item.id}/download`);
}

function confirmDelete(item: FileItem) {
    confirm.require({
        group: 'files',
        message: t('files.confirm_delete', { name: item.name }),
        header: t('files.delete'),
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: t('files.delete'),
        rejectLabel: t('common.cancel'),
        acceptProps: { severity: 'danger' },
        accept: () => router.delete(customerUrl(`/files/${item.id}`), { preserveScroll: true }),
    });
}

function startRename(item: FileItem) {
    renamingId.value = item.id;
    renameValue.value = item.name;
}

function submitRename(item: FileItem) {
    const name = renameValue.value.trim();
    renamingId.value = null;
    if (!name || name === item.name) return;
    router.patch(customerUrl(`/files/${item.id}`), { name }, { preserveScroll: true });
}

function createFolder() {
    newFolderName.value = '';
    newFolderOpen.value = true;
}

function submitNewFolder() {
    const name = newFolderName.value.trim();
    if (!name) return;
    router.post(
        customerUrl('/files/folder'),
        { name, parent_id: currentFolderId.value },
        {
            preserveScroll: true,
            onSuccess: () => { newFolderOpen.value = false; },
        },
    );
}

function onDragStart(item: FileItem, event: DragEvent) {
    draggingId.value = item.id;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(item.id));
    }
}

function onDragEnd() {
    draggingId.value = null;
    dragOverId.value = null;
}

function onDragOverFolder(item: FileItem, event: DragEvent) {
    if (item.type !== 'folder' || draggingId.value === null || draggingId.value === item.id) return;
    event.preventDefault();
    dragOverId.value = item.id;
}

function onDropOnFolder(target: FileItem | null, event: DragEvent) {
    event.preventDefault();
    dragOverId.value = null;
    const id = draggingId.value;
    draggingId.value = null;
    if (id === null) return;
    if (target && target.type !== 'folder') return;
    // No target = dropped on empty space. Stay in the current folder rather
    // than yanking the file to root (classic file-manager behaviour).
    const targetId = target?.id ?? currentFolderId.value;
    if (id === targetId) return;
    const moving = mergedItems.value.find((i) => i.id === id);
    // No-op when already in that folder (drop on blank inside current folder).
    if (moving && moving.parent_id === targetId) return;
    router.patch(customerUrl(`/files/${id}`), { parent_id: targetId }, { preserveScroll: true });
}

function onSearch() {
    router.get(
        customerUrl(currentFolderId.value ? `/files/${currentFolderId.value}` : '/files'),
        { q: searchQuery.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function setViewMode(mode: 'grid' | 'list') {
    viewMode.value = mode;
    localStorage.setItem('files.viewMode', mode);
}

function formatBytes(n: number): string {
    if (!n) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let v = n;
    while (v >= 1024 && i < units.length - 1) {
        v /= 1024;
        i++;
    }
    return `${v.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function iconFor(item: FileItem): string {
    if (item.type === 'folder') return 'pi-folder';
    if (item.is_image) return 'pi-image';
    if (item.mime_type === 'application/pdf') return 'pi-file-pdf';
    if (item.is_video || item.mime_type?.startsWith('video/')) return 'pi-video';
    if (item.mime_type?.startsWith('audio/')) return 'pi-volume-up';
    return 'pi-file';
}

function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        renamingId.value = null;
        uploadOpen.value = false;
        shareDialogFile.value = null;
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => {
    window.removeEventListener('keydown', onKey);
    leaveFilesChannel();
});

// Live patches from the server — dispatched when a queued preview conversion
// finishes or a file is renamed/moved/deleted in another tab. Patch in place
// so the Vue transition animates the thumbnail swap.
const liveItems = reactive<Record<number, Partial<FileItem>>>({});
let fileChannelUserId: number | null = null;

function joinFilesChannel(userId: number) {
    leaveFilesChannel();
    const echo = (window as any).Echo;
    if (!echo) return;
    fileChannelUserId = userId;
    echo.private(`App.Models.User.${userId}`).listen('.FileItemUpdated', (payload: Partial<FileItem> & { id: number }) => {
        liveItems[payload.id] = payload;
    });
}

function leaveFilesChannel() {
    if (fileChannelUserId !== null) {
        (window as any).Echo?.leave(`App.Models.User.${fileChannelUserId}`);
        fileChannelUserId = null;
    }
}

watch(
    () => page.props.auth?.user?.id as number | undefined,
    (id) => {
        if (typeof id === 'number') joinFilesChannel(id);
        else leaveFilesChannel();
    },
    { immediate: true },
);

const uploadUrl = computed(() => customerUrl('/files'));
const extraUploadData = computed(() => ({ parent_id: currentFolderId.value }));

const usageLabel = computed(() => {
    if (props.usage.quota_bytes === null) return t('files.unlimited');
    if (props.usage.quota_bytes === 0) return t('files.disabled');
    return t('files.used_of', {
        used: formatBytes(props.usage.used_bytes),
        quota: formatBytes(props.usage.quota_bytes),
    });
});
</script>

<template>
    <AppLayout>
        <Head :title="t('files.title')" />
        <ConfirmDialog group="files" />

        <div
            class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8"
            @dragover.prevent
            @drop="onDropOnFolder(null, $event)"
        >
            <!-- Toolbar -->
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2 text-sm">
                    <Link :href="customerUrl('/files')" class="text-indigo-600 hover:underline dark:text-indigo-400">
                        {{ t('files.root') }}
                    </Link>
                    <template v-for="(b, i) in breadcrumbs" :key="b.id">
                        <i class="pi pi-chevron-right text-xs text-slate-400" />
                        <Link
                            v-if="i < breadcrumbs.length - 1"
                            :href="customerUrl(`/files/${b.id}`)"
                            class="text-indigo-600 hover:underline dark:text-indigo-400"
                        >
                            {{ b.name }}
                        </Link>
                        <span v-else class="font-medium text-slate-700 dark:text-slate-200">{{ b.name }}</span>
                    </template>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <input
                        v-model="searchQuery"
                        type="search"
                        :placeholder="t('files.search_placeholder')"
                        class="w-48 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-dark-700 dark:bg-dark-900 dark:text-slate-100"
                        @keyup.enter="onSearch"
                    />
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-dark-700 dark:bg-dark-900 dark:text-slate-200 dark:hover:bg-dark-800"
                        @click="createFolder"
                    >
                        <i class="pi pi-folder-plus" />
                        <span>{{ t('files.new_folder') }}</span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500"
                        @click="uploadOpen = true"
                    >
                        <i class="pi pi-upload" />
                        <span>{{ t('files.upload') }}</span>
                    </button>
                    <div class="inline-flex rounded-md border border-slate-300 p-0.5 dark:border-dark-700">
                        <button
                            type="button"
                            class="rounded px-2 py-1 text-sm"
                            :class="viewMode === 'grid' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300' : 'text-slate-500 dark:text-slate-400'"
                            @click="setViewMode('grid')"
                            :title="t('files.view_grid')"
                            :aria-label="t('files.view_grid')"
                        >
                            <i class="pi pi-th-large" />
                        </button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 text-sm"
                            :class="viewMode === 'list' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300' : 'text-slate-500 dark:text-slate-400'"
                            @click="setViewMode('list')"
                            :title="t('files.view_list')"
                            :aria-label="t('files.view_list')"
                        >
                            <i class="pi pi-list" />
                        </button>
                    </div>
                    <Link
                        :href="customerUrl('/files/trash')"
                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 dark:border-dark-700 dark:text-slate-200 dark:hover:bg-dark-800"
                    >
                        <i class="pi pi-trash" />
                        <span>{{ t('files.trash') }}</span>
                    </Link>
                </div>
            </div>

            <!-- Usage bar -->
            <div class="mb-6 rounded-lg border border-slate-200 bg-white px-4 py-3 dark:border-dark-700 dark:bg-dark-900">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-600 dark:text-slate-300">{{ usageLabel }}</span>
                    <span v-if="props.usage.quota_bytes && props.usage.quota_bytes > 0" class="font-medium text-slate-700 dark:text-slate-200">
                        {{ props.usage.percent }}%
                    </span>
                </div>
                <div v-if="props.usage.quota_bytes && props.usage.quota_bytes > 0" class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-dark-800">
                    <div
                        class="h-full transition-all"
                        :class="props.usage.percent >= 95 ? 'bg-rose-500' : props.usage.percent >= 80 ? 'bg-amber-500' : 'bg-indigo-500'"
                        :style="{ width: `${Math.min(100, props.usage.percent)}%` }"
                    />
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-if="mergedItems.length === 0"
                class="rounded-lg border border-dashed border-slate-300 bg-white py-24 text-center dark:border-dark-700 dark:bg-dark-900"
            >
                <i class="pi pi-folder-open text-4xl text-slate-400" />
                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ t('files.empty') }}</p>
            </div>

            <!-- Grid -->
            <TransitionGroup
                v-else-if="viewMode === 'grid'"
                tag="div"
                name="item-fade"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
            >
                <div
                    v-for="item in mergedItems"
                    :key="item.id"
                    class="group relative cursor-pointer overflow-hidden rounded-lg border bg-white transition hover:border-indigo-400 dark:border-dark-700 dark:bg-dark-900"
                    :class="[
                        dragOverId === item.id ? 'ring-2 ring-indigo-500' : 'border-slate-200',
                        draggingId === item.id ? 'opacity-50' : '',
                    ]"
                    draggable="true"
                    @dragstart="onDragStart(item, $event)"
                    @dragend="onDragEnd"
                    @dragover="onDragOverFolder(item, $event)"
                    @dragleave="dragOverId = null"
                    @drop="onDropOnFolder(item, $event)"
                    @click="openItem(item)"
                >
                    <div class="relative flex aspect-square items-center justify-center overflow-hidden bg-slate-50 dark:bg-dark-800">
                        <img
                            v-if="item.thumbnail_url"
                            :src="item.thumbnail_url"
                            :alt="item.name"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                        <i v-else :class="`pi ${iconFor(item)} text-5xl text-slate-400`" />

                        <!-- Video play overlay (ready) -->
                        <div
                            v-if="item.is_video && item.video_ready"
                            class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/0 transition group-hover:bg-black/20"
                        >
                            <div class="rounded-full bg-black/50 p-3 text-white shadow-lg opacity-90 transition group-hover:scale-105 group-hover:opacity-100">
                                <i class="pi pi-play" />
                            </div>
                        </div>

                        <!-- Processing overlay -->
                        <div
                            v-else-if="item.is_video && item.video_processing"
                            class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-1 bg-slate-900/60 text-white text-xs"
                        >
                            <i class="pi pi-spin pi-spinner text-2xl" />
                            <span>{{ t('files.video_processing') }}</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2 px-2 py-1.5 text-xs">
                        <input
                            v-if="renamingId === item.id"
                            v-model="renameValue"
                            type="text"
                            class="flex-1 rounded border border-indigo-300 bg-white px-1 py-0.5 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-indigo-600 dark:bg-dark-900"
                            autofocus
                            @click.stop
                            @keyup.enter="submitRename(item)"
                            @blur="submitRename(item)"
                        />
                        <span v-else class="truncate text-slate-700 dark:text-slate-200" :title="item.name">{{ item.name }}</span>
                    </div>
                    <!-- Actions overlay -->
                    <div class="pointer-events-none absolute right-1 top-1 flex gap-1 opacity-0 transition group-hover:pointer-events-auto group-hover:opacity-100">
                        <button
                            type="button"
                            class="rounded bg-slate-900/70 p-1 text-xs text-white hover:bg-slate-900"
                            :title="t('files.open')"
                            @click.stop="openItem(item)"
                        >
                            <i class="pi pi-external-link" />
                        </button>
                        <button
                            type="button"
                            class="rounded bg-slate-900/70 p-1 text-xs text-white hover:bg-slate-900"
                            :title="t('files.rename')"
                            @click.stop="startRename(item)"
                        >
                            <i class="pi pi-pencil" />
                        </button>
                        <button
                            type="button"
                            class="rounded bg-slate-900/70 p-1 text-xs text-white hover:bg-slate-900"
                            :title="t('files.share')"
                            @click.stop="openShareDialog(item)"
                        >
                            <i class="pi pi-share-alt" />
                        </button>
                        <button
                            type="button"
                            class="rounded bg-rose-600/80 p-1 text-xs text-white hover:bg-rose-600"
                            :title="t('files.delete')"
                            @click.stop="confirmDelete(item)"
                        >
                            <i class="pi pi-trash" />
                        </button>
                    </div>
                </div>
            </TransitionGroup>

            <!-- List -->
            <div
                v-else
                class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-dark-700 dark:bg-dark-900"
            >
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-dark-700">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-dark-800 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ t('files.root') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('files.size') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('files.modified') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-dark-800">
                        <tr
                            v-for="item in mergedItems"
                            :key="item.id"
                            class="hover:bg-slate-50 dark:hover:bg-dark-800"
                            :class="[dragOverId === item.id ? 'ring-1 ring-inset ring-indigo-500' : '', draggingId === item.id ? 'opacity-50' : '']"
                            draggable="true"
                            @dragstart="onDragStart(item, $event)"
                            @dragend="onDragEnd"
                            @dragover="onDragOverFolder(item, $event)"
                            @drop="onDropOnFolder(item, $event)"
                        >
                            <td class="flex items-center gap-2 px-4 py-2">
                                <i :class="`pi ${iconFor(item)} text-slate-500`" />
                                <button v-if="renamingId !== item.id" type="button" class="truncate text-left hover:underline" @click="openItem(item)">
                                    {{ item.name }}
                                </button>
                                <input
                                    v-else
                                    v-model="renameValue"
                                    type="text"
                                    class="rounded border border-indigo-300 bg-white px-1 py-0.5 text-xs dark:border-indigo-600 dark:bg-dark-900"
                                    autofocus
                                    @click.stop
                                    @keyup.enter="submitRename(item)"
                                    @blur="submitRename(item)"
                                />
                            </td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">
                                {{ item.type === 'file' ? formatBytes(item.size) : '—' }}
                            </td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">
                                {{ item.updated_at ? new Date(item.updated_at).toLocaleString() : '—' }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" class="rounded p-1 hover:bg-slate-100 dark:hover:bg-dark-700" :title="t('files.rename')" @click="startRename(item)">
                                        <i class="pi pi-pencil text-slate-500" />
                                    </button>
                                    <a
                                        v-if="item.type === 'file'"
                                        :href="customerUrl(`/files/${item.id}/download`)"
                                        class="rounded p-1 hover:bg-slate-100 dark:hover:bg-dark-700"
                                        :title="t('files.download')"
                                    >
                                        <i class="pi pi-download text-slate-500" />
                                    </a>
                                    <button type="button" class="rounded p-1 hover:bg-rose-50 dark:hover:bg-rose-900/40" :title="t('files.delete')" @click="confirmDelete(item)">
                                        <i class="pi pi-trash text-rose-500" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <UploadDialog
            v-model:open="uploadOpen"
            :upload-url="uploadUrl"
            :extra-data="extraUploadData"
            :max-file-size="50"
            :multiple="true"
        />

        <ImageLightbox v-if="lightboxItems.length" v-model="lightboxIndex" :items="lightboxItems" />

        <VideoPlayer
            v-model="videoOpen"
            :src="videoItem?.video_web_url ?? null"
            :poster="videoItem?.video_poster_url ?? videoItem?.thumbnail_url ?? null"
            :title="videoItem?.name ?? null"
        />

        <!-- New folder dialog -->
        <Dialog
            v-model:visible="newFolderOpen"
            :header="t('files.new_folder')"
            modal
            :style="{ width: '24rem' }"
            :draggable="false"
            @show="() => { /* autofocus hook */ }"
        >
            <form @submit.prevent="submitNewFolder" class="space-y-3">
                <label class="block text-sm">
                    <span class="mb-1 block text-slate-600 dark:text-slate-300">{{ t('common.name') }}</span>
                    <InputText
                        v-model="newFolderName"
                        class="w-full"
                        autofocus
                        :placeholder="t('files.new_folder')"
                        @keyup.enter="submitNewFolder"
                    />
                </label>
            </form>
            <template #footer>
                <Button :label="t('common.cancel')" severity="secondary" text @click="newFolderOpen = false" />
                <Button :label="t('files.new_folder')" icon="pi pi-folder-plus" @click="submitNewFolder" />
            </template>
        </Dialog>

        <!-- Share dialog -->
        <div
            v-if="shareDialogFile"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="shareDialogFile = null"
        >
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-dark-900">
                <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-slate-800 dark:text-slate-100">
                    <i class="pi pi-share-alt text-indigo-500" />
                    {{ t('files.share_title', { name: shareDialogFile.name }) }}
                </h2>

                <div v-if="!shareResultUrl" class="space-y-3">
                    <label class="flex items-center justify-between gap-3 text-sm">
                        <span>{{ t('files.share_expiry') }}</span>
                        <select v-model.number="shareHours" class="rounded border border-slate-300 bg-white px-2 py-1 text-sm dark:border-dark-700 dark:bg-dark-800 dark:text-slate-100">
                            <option :value="1">{{ t('files.share_expiry_hours', { count: 1 }) }}</option>
                            <option :value="24">{{ t('files.share_expiry_hours', { count: 24 }) }}</option>
                            <option :value="72">{{ t('files.share_expiry_days', { count: 3 }) }}</option>
                            <option :value="168">{{ t('files.share_expiry_days', { count: 7 }) }}</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block">{{ t('files.share_password_optional') }}</span>
                        <input
                            v-model="sharePassword"
                            type="text"
                            :placeholder="t('files.share_password_placeholder')"
                            class="w-full rounded border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-dark-700 dark:bg-dark-800 dark:text-slate-100"
                        />
                    </label>
                </div>

                <div v-else class="space-y-2 text-sm">
                    <p class="text-emerald-600 dark:text-emerald-400">
                        <i class="pi pi-check-circle mr-1" />
                        {{ t('files.share_created_copied') }}
                    </p>
                    <div class="truncate rounded bg-slate-100 px-2 py-1.5 font-mono text-xs text-slate-700 dark:bg-dark-800 dark:text-slate-300">
                        {{ shareResultUrl }}
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        v-if="shareDialogFile?.type === 'file' && !shareResultUrl"
                        type="button"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 dark:border-dark-700 dark:text-slate-200 dark:hover:bg-dark-800"
                        @click="quickShare(shareDialogFile)"
                    >
                        {{ t('files.quick_link') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 dark:border-dark-700 dark:text-slate-200 dark:hover:bg-dark-800"
                        @click="shareDialogFile = null"
                    >
                        {{ t('common.close') }}
                    </button>
                    <button
                        v-if="!shareResultUrl"
                        type="button"
                        :disabled="shareCreating"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                        @click="createShare"
                    >
                        <i class="pi pi-link mr-1" />
                        {{ t('files.create_share') }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.item-fade-move,
.item-fade-enter-active,
.item-fade-leave-active {
    transition: all 260ms ease;
}
.item-fade-enter-from {
    opacity: 0;
    transform: translateY(6px) scale(0.98);
}
.item-fade-leave-to {
    opacity: 0;
    transform: scale(0.96);
}
.item-fade-leave-active {
    position: absolute;
}
</style>
