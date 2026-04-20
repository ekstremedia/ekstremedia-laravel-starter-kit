<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, CheckCircle, Upload, X } from 'lucide-vue-next';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

// ============ Types ============
interface UploadingFile {
    id: string;
    file: File;
    name: string;
    size: number;
    progress: number;
    status: 'pending' | 'uploading' | 'complete' | 'error';
    error?: string;
}

// ============ Props & Emits ============
const props = withDefaults(
    defineProps<{
        open: boolean;
        uploadUrl: string;
        extraData?: Record<string, string | number | null>;
        maxFileSize?: number; // in MB
        multiple?: boolean;
        accept?: string;
    }>(),
    {
        maxFileSize: 2048,
        multiple: true,
        accept: '*/*',
        extraData: () => ({}),
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    'file-uploaded': [fileName: string];
    'all-complete': [];
    error: [error: string];
}>();

// ============ State ============
const { t } = useI18n();
const fileInputRef = ref<HTMLInputElement | null>(null);
const fileListRef = ref<HTMLElement | null>(null);
const fileItemRefs = ref<Map<string, HTMLElement>>(new Map());
const isDragging = ref(false);
const dragCounter = ref(0);

// Upload tracking
const files = ref<UploadingFile[]>([]);
const rejectedFiles = ref<{ name: string; size: number; reason: string }[]>([]);
const isUploading = ref(false);
const currentFileIndex = ref(-1);
const allComplete = ref(false);
let autoCloseTimeout: ReturnType<typeof setTimeout> | null = null;

onUnmounted(() => {
    if (autoCloseTimeout) {
        clearTimeout(autoCloseTimeout);
        autoCloseTimeout = null;
    }
});

// Auto-scroll to current uploading file
function scrollToCurrentFile() {
    if (currentFileIndex.value < 0 || currentFileIndex.value >= files.value.length) return;

    const currentFile = files.value[currentFileIndex.value];
    const element = fileItemRefs.value.get(currentFile.id);

    if (element && fileListRef.value) {
        // Calculate if element is visible in the container
        const container = fileListRef.value;
        const containerRect = container.getBoundingClientRect();
        const elementRect = element.getBoundingClientRect();

        // Check if element is below the visible area
        const isBelow = elementRect.bottom > containerRect.bottom;
        // Check if element is above the visible area
        const isAbove = elementRect.top < containerRect.top;

        if (isBelow || isAbove) {
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

// Set ref for file item
function setFileItemRef(id: string, el: HTMLElement | null) {
    if (el) {
        fileItemRefs.value.set(id, el);
    } else {
        fileItemRefs.value.delete(id);
    }
}

// ============ Computed ============
const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

const canClose = computed(() => {
    // Can close when: not uploading AND (no files OR all uploads finished)
    return !isUploading.value;
});

const hasErrors = computed(() => {
    return files.value.some((f) => f.status === 'error');
});

const totalProgress = computed(() => {
    if (files.value.length === 0) return 0;
    const total = files.value.reduce((sum, f) => sum + f.progress, 0);
    return Math.round(total / files.value.length);
});

const completedCount = computed(() => {
    return files.value.filter((f) => f.status === 'complete').length;
});

// ============ Helpers ============
function generateId(): string {
    return Math.random().toString(36).substring(2, 9);
}

function formatSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

// ============ Methods ============
function resetState() {
    // Clear any pending auto-close timeout
    if (autoCloseTimeout) {
        clearTimeout(autoCloseTimeout);
        autoCloseTimeout = null;
    }
    files.value = [];
    rejectedFiles.value = [];
    fileItemRefs.value.clear();
    isUploading.value = false;
    currentFileIndex.value = -1;
    allComplete.value = false;
    isDragging.value = false;
    dragCounter.value = 0;
}

function close() {
    if (canClose.value) {
        resetState();
        isOpen.value = false;
    }
}

function handleBackdropClick() {
    if (canClose.value) {
        close();
    }
}

function validateAndProcessFiles(fileList: FileList | File[]): UploadingFile[] {
    const fileArray = Array.from(fileList);
    const validFiles: UploadingFile[] = [];
    const rejected: { name: string; size: number; reason: string }[] = [];

    for (const file of fileArray) {
        if (file.size > props.maxFileSize * 1024 * 1024) {
            rejected.push({
                name: file.name,
                size: file.size,
                reason: t('upload.fileTooLarge', { size: props.maxFileSize }),
            });
            continue;
        }
        validFiles.push({
            id: generateId(),
            file,
            name: file.name,
            size: file.size,
            progress: 0,
            status: 'pending',
        });
    }

    // Update rejected files state
    rejectedFiles.value = rejected;

    return validFiles;
}

function handleFiles(fileList: FileList | File[]) {
    const validFiles = validateAndProcessFiles(fileList);

    if (validFiles.length === 0) {
        return;
    }

    // Add files to the list
    files.value = validFiles;

    // Start uploading
    startUpload();
}

async function startUpload() {
    if (isUploading.value || files.value.length === 0) return;

    isUploading.value = true;
    currentFileIndex.value = 0;

    // Upload files one by one using Inertia router
    for (let i = 0; i < files.value.length; i++) {
        currentFileIndex.value = i;
        const uploadFile = files.value[i];

        try {
            uploadFile.status = 'uploading';

            // Scroll to keep current file visible
            await nextTick();
            scrollToCurrentFile();

            // Use Inertia router.post with progress tracking
            await new Promise<void>((resolve) => {
                router.post(
                    props.uploadUrl,
                    {
                        files: [uploadFile.file],
                        ...props.extraData,
                    },
                    {
                        forceFormData: true,
                        preserveScroll: true,
                        preserveState: true,
                        onProgress: (progress) => {
                            if (progress?.percentage) {
                                uploadFile.progress = progress.percentage;
                            }
                        },
                        onSuccess: () => {
                            uploadFile.status = 'complete';
                            uploadFile.progress = 100;
                            emit('file-uploaded', uploadFile.name);
                            resolve();
                        },
                        onError: (errors) => {
                            const fallback = t('upload.failed');
                            const errorMsg = Object.values(errors).flat().join(', ') || fallback;
                            uploadFile.status = 'error';
                            uploadFile.error = errorMsg;
                            emit('error', errorMsg ?? fallback);
                            resolve(); // Continue to next file even on error
                        },
                    },
                );
            });
        } catch (error: any) {
            const msg = (error?.message ?? t('upload.failed')) as string;
            uploadFile.status = 'error';
            uploadFile.error = msg;
            emit('error', msg);
        }
    }

    // All done
    isUploading.value = false;
    currentFileIndex.value = -1;

    // Check if all completed successfully
    const allSuccessful = files.value.every((f) => f.status === 'complete');
    if (allSuccessful) {
        allComplete.value = true;
        emit('all-complete');

        // Auto-close after showing completion (3.5 seconds for better feedback)
        autoCloseTimeout = setTimeout(() => {
            autoCloseTimeout = null;
            resetState();
            isOpen.value = false;
        }, 3500);
    }
}

// Drag and drop
function onDragEnter(e: DragEvent) {
    e.preventDefault();
    e.stopPropagation();
    dragCounter.value++;
    isDragging.value = true;
}

function onDragLeave(e: DragEvent) {
    e.preventDefault();
    e.stopPropagation();
    dragCounter.value--;
    if (dragCounter.value === 0) {
        isDragging.value = false;
    }
}

function onDragOver(e: DragEvent) {
    e.preventDefault();
    e.stopPropagation();
}

function onDrop(e: DragEvent) {
    e.preventDefault();
    e.stopPropagation();
    isDragging.value = false;
    dragCounter.value = 0;

    const droppedFiles = e.dataTransfer?.files;
    if (droppedFiles && droppedFiles.length > 0) {
        handleFiles(droppedFiles);
    }
}

// File input
function openFilePicker() {
    fileInputRef.value?.click();
}

function onFileInputChange(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
        handleFiles(input.files);
        input.value = ''; // Reset input
    }
}

// Reset when dialog opens
watch(
    () => props.open,
    (newValue) => {
        if (newValue) {
            resetState();
        }
    },
);

// Expose for parent if needed
defineExpose({
    resetState,
    handleFiles,
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="isOpen"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                @click.self="handleBackdropClick"
            >
                <div class="bg-surface-900/95 w-full max-w-2xl overflow-hidden rounded-2xl border border-purple-500/20 shadow-2xl backdrop-blur-xl">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-purple-500/20 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white">{{ t('share.upload') }}</h3>
                        <button
                            v-if="canClose"
                            type="button"
                            class="rounded-lg p-2 text-gray-400 transition-colors hover:bg-purple-500/20 hover:text-white"
                            @click="close"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="p-6">
                        <!-- State 1: All Complete -->
                        <div v-if="allComplete" class="py-8">
                            <div class="flex flex-col items-center justify-center gap-4">
                                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-green-500/20">
                                    <CheckCircle class="h-12 w-12 text-green-400" />
                                </div>
                                <span class="text-xl font-medium text-green-300">
                                    {{ t('upload.filesUploaded', files.length) }}
                                </span>
                            </div>
                        </div>

                        <!-- State 2: Uploading Files -->
                        <div v-else-if="files.length > 0" class="py-4">
                            <!-- Overall progress header -->
                            <div class="mb-4 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Upload v-if="isUploading" class="h-5 w-5 animate-bounce text-purple-400" />
                                    <CheckCircle v-else class="h-5 w-5 text-green-400" />
                                    <span class="text-sm font-medium text-purple-300">
                                        {{ isUploading ? t('upload.uploadingFiles', files.length) : t('upload.complete') }}
                                    </span>
                                </div>
                                <span class="text-sm text-purple-400">{{ completedCount }} / {{ files.length }}</span>
                            </div>

                            <!-- Overall progress bar -->
                            <div class="mb-6 h-2 overflow-hidden rounded-full bg-purple-900/50">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-purple-500 to-violet-500 transition-all duration-300"
                                    :style="{ width: `${totalProgress}%` }"
                                ></div>
                            </div>

                            <!-- File list with individual progress -->
                            <div ref="fileListRef" class="max-h-80 space-y-3 overflow-y-auto scroll-smooth">
                                <div
                                    v-for="(uploadFile, index) in files"
                                    :key="uploadFile.id"
                                    :ref="(el) => setFileItemRef(uploadFile.id, el as HTMLElement)"
                                    :class="[
                                        'rounded-lg border p-3 transition-all',
                                        uploadFile.status === 'complete'
                                            ? 'border-green-500/30 bg-green-900/20'
                                            : uploadFile.status === 'error'
                                              ? 'border-red-500/30 bg-red-900/20'
                                              : uploadFile.status === 'uploading'
                                                ? 'border-purple-500/30 bg-purple-900/20'
                                                : 'border-purple-500/10 bg-purple-900/10',
                                    ]"
                                >
                                    <!-- File info row -->
                                    <div class="mb-2 flex items-center gap-3">
                                        <!-- Status icon -->
                                        <div class="flex-shrink-0">
                                            <div
                                                v-if="uploadFile.status === 'complete'"
                                                class="flex h-6 w-6 items-center justify-center rounded-full bg-green-500"
                                            >
                                                <Check class="h-4 w-4 text-white" />
                                            </div>
                                            <div
                                                v-else-if="uploadFile.status === 'error'"
                                                class="flex h-6 w-6 items-center justify-center rounded-full bg-red-500"
                                            >
                                                <X class="h-4 w-4 text-white" />
                                            </div>
                                            <div
                                                v-else-if="uploadFile.status === 'uploading'"
                                                class="h-6 w-6 animate-spin rounded-full border-2 border-purple-500 border-t-transparent"
                                            ></div>
                                            <div
                                                v-else
                                                class="flex h-6 w-6 items-center justify-center rounded-full bg-purple-500/30 text-xs font-bold text-purple-300"
                                            >
                                                {{ index + 1 }}
                                            </div>
                                        </div>

                                        <!-- File name and size -->
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="truncate text-sm font-medium"
                                                :class="
                                                    uploadFile.status === 'complete'
                                                        ? 'text-green-300'
                                                        : uploadFile.status === 'error'
                                                          ? 'text-red-300'
                                                          : 'text-purple-200'
                                                "
                                            >
                                                {{ uploadFile.name }}
                                            </p>
                                            <p class="text-xs text-purple-400/70">{{ formatSize(uploadFile.size) }}</p>
                                        </div>

                                        <!-- Progress percentage -->
                                        <span
                                            class="flex-shrink-0 text-sm font-medium"
                                            :class="
                                                uploadFile.status === 'complete'
                                                    ? 'text-green-400'
                                                    : uploadFile.status === 'error'
                                                      ? 'text-red-400'
                                                      : 'text-purple-400'
                                            "
                                        >
                                            {{ uploadFile.progress }}%
                                        </span>
                                    </div>

                                    <!-- Individual progress bar -->
                                    <div class="h-1.5 overflow-hidden rounded-full bg-purple-900/50">
                                        <div
                                            :class="[
                                                'h-full rounded-full transition-all duration-300',
                                                uploadFile.status === 'complete'
                                                    ? 'bg-green-500'
                                                    : uploadFile.status === 'error'
                                                      ? 'bg-red-500'
                                                      : 'bg-gradient-to-r from-purple-500 to-violet-500',
                                            ]"
                                            :style="{ width: `${uploadFile.progress}%` }"
                                        ></div>
                                    </div>

                                    <!-- Error message -->
                                    <p v-if="uploadFile.error" class="mt-2 text-xs text-red-400">
                                        {{ uploadFile.error }}
                                    </p>
                                </div>
                            </div>

                            <!-- Close button when there are errors -->
                            <div v-if="hasErrors && !isUploading" class="mt-6 flex justify-end">
                                <button
                                    type="button"
                                    class="rounded-lg bg-purple-600 px-6 py-2.5 text-sm font-medium text-white transition-colors hover:bg-purple-500"
                                    @click="close"
                                >
                                    {{ t('common.close') }}
                                </button>
                            </div>
                        </div>

                        <!-- State 3: File Selection (Dropzone) -->
                        <div
                            v-else
                            :class="[
                                'relative cursor-pointer rounded-xl border-2 border-dashed p-8 transition-all duration-200',
                                isDragging
                                    ? 'border-purple-500 bg-purple-500/10'
                                    : 'border-purple-500/30 bg-purple-900/10 hover:border-purple-500/50 hover:bg-purple-900/20',
                            ]"
                            @click="openFilePicker"
                            @dragenter="onDragEnter"
                            @dragleave="onDragLeave"
                            @dragover="onDragOver"
                            @drop="onDrop"
                        >
                            <input ref="fileInputRef" type="file" class="hidden" :multiple="multiple" :accept="accept" @change="onFileInputChange" />

                            <div class="flex flex-col items-center justify-center text-center">
                                <div class="mb-4">
                                    <Upload class="h-12 w-12" :class="isDragging ? 'animate-bounce text-purple-400' : 'text-purple-500/70'" />
                                </div>
                                <p class="text-base font-medium" :class="isDragging ? 'text-purple-300' : 'text-gray-300'">
                                    {{ isDragging ? t('upload.dropHere') : t('upload.dragAndDrop') }}
                                </p>
                                <p class="mt-2 text-sm text-gray-500">
                                    {{ t('upload.or') }}
                                    <span class="font-medium text-purple-400">
                                        {{ t('upload.browse') }}
                                    </span>
                                </p>
                                <p class="mt-3 text-xs text-gray-600">
                                    {{ t('upload.maxSize', { size: maxFileSize }) }}
                                </p>
                            </div>

                            <!-- Rejected files warning -->
                            <div
                                v-if="rejectedFiles.length > 0"
                                class="mt-6 w-full rounded-lg border border-red-500/30 bg-red-500/10 p-4"
                                @click.stop
                            >
                                <div class="flex items-start gap-3">
                                    <div class="shrink-0">
                                        <X class="h-5 w-5 text-red-400" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-red-300">
                                            {{ t('upload.filesRejected', rejectedFiles.length) }}
                                        </p>
                                        <ul class="mt-2 space-y-1">
                                            <li v-for="(file, index) in rejectedFiles" :key="index" class="text-xs text-red-400/80">
                                                <span class="font-medium">{{ file.name }}</span>
                                                <span class="text-red-400/60"> ({{ formatSize(file.size) }}) - {{ file.reason }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                    <button
                                        type="button"
                                        class="shrink-0 text-red-400 transition-colors hover:text-red-300"
                                        @click.stop="rejectedFiles = []"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* Modal transition */
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-active > div,
.modal-leave-active > div {
    transition:
        transform 0.3s ease,
        opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from > div,
.modal-leave-to > div {
    transform: scale(0.95) translateY(-10px);
    opacity: 0;
}
</style>
