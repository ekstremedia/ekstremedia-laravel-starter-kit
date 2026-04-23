<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, CheckCircle, Upload, X } from 'lucide-vue-next';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import CommandDialog from '@/Components/Command/Dialog.vue';
import CmdButton from '@/Components/Command/Button.vue';

interface UploadingFile {
    id: string;
    file: File;
    name: string;
    size: number;
    progress: number;
    status: 'pending' | 'uploading' | 'complete' | 'error';
    error?: string;
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        uploadUrl: string;
        extraData?: Record<string, string | number | null>;
        maxFileSize?: number;
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

const { t } = useI18n();
const fileInputRef = ref<HTMLInputElement | null>(null);
const fileListRef = ref<HTMLElement | null>(null);
const fileItemRefs = ref<Map<string, HTMLElement>>(new Map());
const isDragging = ref(false);
const dragCounter = ref(0);

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

function scrollToCurrentFile() {
    if (currentFileIndex.value < 0 || currentFileIndex.value >= files.value.length) return;
    const currentFile = files.value[currentFileIndex.value];
    const element = fileItemRefs.value.get(currentFile.id);
    if (element && fileListRef.value) {
        const container = fileListRef.value;
        const containerRect = container.getBoundingClientRect();
        const elementRect = element.getBoundingClientRect();
        const isBelow = elementRect.bottom > containerRect.bottom;
        const isAbove = elementRect.top < containerRect.top;
        if (isBelow || isAbove) {
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

function setFileItemRef(id: string, el: HTMLElement | null) {
    if (el) {
        fileItemRefs.value.set(id, el);
    } else {
        fileItemRefs.value.delete(id);
    }
}

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

const canClose = computed(() => !isUploading.value);
const hasErrors = computed(() => files.value.some((f) => f.status === 'error'));
const totalProgress = computed(() => {
    if (files.value.length === 0) return 0;
    const total = files.value.reduce((sum, f) => sum + f.progress, 0);
    return Math.round(total / files.value.length);
});
const completedCount = computed(() => files.value.filter((f) => f.status === 'complete').length);

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

function resetState() {
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

function onVisibleChange(v: boolean) {
    if (!v && canClose.value) {
        resetState();
        isOpen.value = false;
    }
}

function validateAndProcessFiles(fileList: FileList | File[]): UploadingFile[] {
    const fileArray = Array.from(fileList);
    const validFiles: UploadingFile[] = [];
    const rejected: { name: string; size: number; reason: string }[] = [];
    const singleOnly = props.multiple === false;

    for (const file of fileArray) {
        if (file.size > props.maxFileSize * 1024 * 1024) {
            rejected.push({ name: file.name, size: file.size, reason: t('upload.fileTooLarge', { size: props.maxFileSize }) });
            continue;
        }
        if (singleOnly && validFiles.length >= 1) {
            rejected.push({ name: file.name, size: file.size, reason: t('upload.onlyOneFile') });
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

    rejectedFiles.value = [...rejectedFiles.value, ...rejected];
    return validFiles;
}

function handleFiles(fileList: FileList | File[]) {
    const validFiles = validateAndProcessFiles(fileList);
    if (validFiles.length === 0) return;
    files.value = validFiles;
    startUpload();
}

async function startUpload() {
    if (isUploading.value || files.value.length === 0) return;

    isUploading.value = true;
    currentFileIndex.value = 0;

    for (let i = 0; i < files.value.length; i++) {
        currentFileIndex.value = i;
        const uploadFile = files.value[i];

        try {
            uploadFile.status = 'uploading';
            await nextTick();
            scrollToCurrentFile();

            await new Promise<void>((resolve) => {
                let settled = false;
                const settle = () => {
                    if (!settled) {
                        settled = true;
                        resolve();
                    }
                };
                router.post(
                    props.uploadUrl,
                    { files: [uploadFile.file], ...props.extraData },
                    {
                        forceFormData: true,
                        preserveScroll: true,
                        preserveState: true,
                        onProgress: (progress) => {
                            if (progress && typeof progress.percentage === 'number') {
                                uploadFile.progress = progress.percentage;
                            }
                        },
                        onSuccess: () => {
                            uploadFile.status = 'complete';
                            uploadFile.progress = 100;
                            emit('file-uploaded', uploadFile.name);
                            settle();
                        },
                        onError: (errors) => {
                            const fallback = t('upload.failed');
                            const errorMsg = Object.values(errors).flat().join(', ') || fallback;
                            uploadFile.status = 'error';
                            uploadFile.error = errorMsg;
                            emit('error', errorMsg ?? fallback);
                            settle();
                        },
                        onCancel: () => {
                            const msg = t('upload.cancelled');
                            uploadFile.status = 'error';
                            uploadFile.error = msg;
                            emit('error', msg);
                            settle();
                        },
                        onFinish: () => {
                            settle();
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

    isUploading.value = false;
    currentFileIndex.value = -1;

    const allSuccessful = files.value.every((f) => f.status === 'complete');
    if (allSuccessful) {
        allComplete.value = true;
        emit('all-complete');
        autoCloseTimeout = setTimeout(() => {
            autoCloseTimeout = null;
            resetState();
            isOpen.value = false;
        }, 3500);
    }
}

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
    if (dragCounter.value === 0) isDragging.value = false;
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

function openFilePicker() {
    fileInputRef.value?.click();
}

function onFileInputChange(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
        handleFiles(input.files);
        input.value = '';
    }
}

watch(
    () => props.open,
    (newValue) => {
        if (newValue) resetState();
    },
);

function statusBorder(f: UploadingFile) {
    if (f.status === 'complete') return 'rgba(94, 229, 154, 0.33)';
    if (f.status === 'error') return 'rgba(255, 138, 138, 0.33)';
    if (f.status === 'uploading') return 'var(--accent-border)';
    return 'var(--border)';
}

function statusBg(f: UploadingFile) {
    if (f.status === 'complete') return 'rgba(94, 229, 154, 0.06)';
    if (f.status === 'error') return 'rgba(255, 138, 138, 0.06)';
    if (f.status === 'uploading') return 'var(--accent-soft)';
    return 'var(--panel2)';
}

function statusFg(f: UploadingFile) {
    if (f.status === 'complete') return 'var(--success)';
    if (f.status === 'error') return 'var(--danger)';
    if (f.status === 'uploading') return 'var(--accent)';
    return 'var(--fg-dim)';
}

function barFill(f: UploadingFile) {
    if (f.status === 'complete') return 'var(--success)';
    if (f.status === 'error') return 'var(--danger)';
    return 'var(--accent)';
}

defineExpose({
    resetState,
    handleFiles,
});
</script>

<template>
    <CommandDialog
        :visible="isOpen"
        :title="t('share.upload')"
        width="640px"
        :close-on-backdrop="canClose"
        :show-close="canClose"
        :padded="false"
        @update:visible="onVisibleChange"
    >
        <!-- State 1: All complete -->
        <div v-if="allComplete" :style="{ padding: '40px 24px', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '16px' }">
            <div
                :style="{
                    width: '72px',
                    height: '72px',
                    borderRadius: '50%',
                    background: 'rgba(94, 229, 154, 0.12)',
                    border: '1px solid rgba(94, 229, 154, 0.33)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    color: 'var(--success)',
                }"
            >
                <CheckCircle :style="{ width: '40px', height: '40px' }" />
            </div>
            <span :style="{ fontSize: '16px', fontWeight: 500, color: 'var(--success)' }">
                {{ t('upload.filesUploaded', files.length) }}
            </span>
        </div>

        <!-- State 2: Uploading files -->
        <div v-else-if="files.length > 0" :style="{ padding: '16px' }">
            <div :style="{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '10px' }">
                <div :style="{ display: 'flex', alignItems: 'center', gap: '8px' }">
                    <Upload v-if="isUploading" :style="{ width: '16px', height: '16px', color: 'var(--accent)', animation: 'cmdBounce 0.9s infinite' }" />
                    <CheckCircle v-else :style="{ width: '16px', height: '16px', color: 'var(--success)' }" />
                    <span :style="{ fontSize: '12.5px', fontWeight: 500, color: 'var(--fg)' }">
                        {{ isUploading ? t('upload.uploadingFiles', files.length) : t('upload.complete') }}
                    </span>
                </div>
                <span class="cmd-mono" :style="{ fontSize: '11.5px', color: 'var(--fg-dim)' }">{{ completedCount }} / {{ files.length }}</span>
            </div>

            <div :style="{ height: '6px', borderRadius: '999px', background: 'var(--panel2)', overflow: 'hidden', marginBottom: '16px' }">
                <div
                    :style="{
                        height: '100%',
                        width: `${totalProgress}%`,
                        background: 'var(--accent)',
                        transition: 'width 0.25s ease',
                    }"
                ></div>
            </div>

            <div
                ref="fileListRef"
                :style="{ maxHeight: '320px', overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: '8px', scrollBehavior: 'smooth' }"
            >
                <div
                    v-for="(uploadFile, index) in files"
                    :key="uploadFile.id"
                    :ref="(el) => setFileItemRef(uploadFile.id, el as HTMLElement)"
                    :style="{
                        border: `1px solid ${statusBorder(uploadFile)}`,
                        background: statusBg(uploadFile),
                        borderRadius: '6px',
                        padding: '10px 12px',
                        transition: 'background 0.12s, border-color 0.12s',
                    }"
                >
                    <div :style="{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '6px' }">
                        <div :style="{ flexShrink: 0 }">
                            <div
                                v-if="uploadFile.status === 'complete'"
                                :style="{ width: '22px', height: '22px', borderRadius: '50%', background: 'var(--success)', display: 'flex', alignItems: 'center', justifyContent: 'center' }"
                            >
                                <Check :style="{ width: '14px', height: '14px', color: '#000' }" />
                            </div>
                            <div
                                v-else-if="uploadFile.status === 'error'"
                                :style="{ width: '22px', height: '22px', borderRadius: '50%', background: 'var(--danger)', display: 'flex', alignItems: 'center', justifyContent: 'center' }"
                            >
                                <X :style="{ width: '14px', height: '14px', color: '#000' }" />
                            </div>
                            <div
                                v-else-if="uploadFile.status === 'uploading'"
                                :style="{ width: '22px', height: '22px', borderRadius: '50%', border: '2px solid var(--accent)', borderTopColor: 'transparent', animation: 'cmdSpin 0.7s linear infinite' }"
                            ></div>
                            <div
                                v-else
                                class="cmd-mono"
                                :style="{ width: '22px', height: '22px', borderRadius: '50%', background: 'var(--panel2)', border: '1px solid var(--border)', color: 'var(--fg-dim)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '10px', fontWeight: 600 }"
                            >
                                {{ index + 1 }}
                            </div>
                        </div>

                        <div :style="{ minWidth: 0, flex: 1 }">
                            <p :style="{ margin: 0, fontSize: '12.5px', fontWeight: 500, color: statusFg(uploadFile), overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }">
                                {{ uploadFile.name }}
                            </p>
                            <p class="cmd-mono" :style="{ margin: 0, fontSize: '10.5px', color: 'var(--fg-mute)' }">{{ formatSize(uploadFile.size) }}</p>
                        </div>

                        <span
                            class="cmd-mono"
                            :style="{ flexShrink: 0, fontSize: '12px', fontWeight: 500, color: statusFg(uploadFile) }"
                        >
                            {{ uploadFile.progress }}%
                        </span>
                    </div>

                    <div :style="{ height: '4px', borderRadius: '999px', background: 'var(--panel2)', overflow: 'hidden' }">
                        <div
                            :style="{
                                height: '100%',
                                width: `${uploadFile.progress}%`,
                                background: barFill(uploadFile),
                                transition: 'width 0.25s ease',
                            }"
                        ></div>
                    </div>

                    <p v-if="uploadFile.error" :style="{ marginTop: '6px', fontSize: '11px', color: 'var(--danger)' }">
                        {{ uploadFile.error }}
                    </p>
                </div>
            </div>

            <div v-if="hasErrors && !isUploading" :style="{ marginTop: '16px', display: 'flex', justifyContent: 'flex-end' }">
                <CmdButton variant="primary" size="sm" @click="close">
                    {{ t('common.close') }}
                </CmdButton>
            </div>
        </div>

        <!-- State 3: Dropzone -->
        <div v-else :style="{ padding: '16px' }">
            <div
                role="button"
                tabindex="0"
                :aria-label="t('upload.dragAndDrop')"
                :style="{
                    position: 'relative',
                    cursor: 'pointer',
                    borderRadius: '8px',
                    border: `2px dashed ${isDragging ? 'var(--accent)' : 'var(--border)'}`,
                    background: isDragging ? 'var(--accent-soft)' : 'var(--panel2)',
                    padding: '32px 24px',
                    transition: 'background 0.18s, border-color 0.18s',
                    outline: 'none',
                }"
                @click="openFilePicker"
                @keydown.enter.prevent="openFilePicker"
                @keydown.space.prevent="openFilePicker"
                @dragenter="onDragEnter"
                @dragleave="onDragLeave"
                @dragover="onDragOver"
                @drop="onDrop"
            >
                <input
                    ref="fileInputRef"
                    type="file"
                    :multiple="multiple"
                    :accept="accept"
                    :style="{ display: 'none' }"
                    @change="onFileInputChange"
                />

                <div :style="{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '10px', textAlign: 'center' }">
                    <Upload
                        :style="{
                            width: '44px',
                            height: '44px',
                            color: isDragging ? 'var(--accent)' : 'var(--fg-mute)',
                            animation: isDragging ? 'cmdBounce 0.9s infinite' : 'none',
                        }"
                    />
                    <p :style="{ margin: 0, fontSize: '14px', fontWeight: 500, color: isDragging ? 'var(--accent)' : 'var(--fg)' }">
                        {{ isDragging ? t('upload.dropHere') : t('upload.dragAndDrop') }}
                    </p>
                    <p :style="{ margin: 0, fontSize: '12px', color: 'var(--fg-dim)' }">
                        {{ t('upload.or') }}
                        <span :style="{ color: 'var(--accent)', fontWeight: 500 }">{{ t('upload.browse') }}</span>
                    </p>
                    <p class="cmd-mono" :style="{ margin: 0, fontSize: '10.5px', color: 'var(--fg-mute)' }">
                        {{ t('upload.maxSize', { size: maxFileSize }) }}
                    </p>
                </div>
            </div>

            <div
                v-if="rejectedFiles.length > 0"
                :style="{
                    marginTop: '14px',
                    borderRadius: '6px',
                    border: '1px solid rgba(255, 138, 138, 0.33)',
                    background: 'rgba(255, 138, 138, 0.08)',
                    padding: '12px',
                }"
                @click.stop
            >
                <div :style="{ display: 'flex', alignItems: 'flex-start', gap: '10px' }">
                    <X :style="{ width: '16px', height: '16px', color: 'var(--danger)', flexShrink: 0, marginTop: '2px' }" />
                    <div :style="{ minWidth: 0, flex: 1 }">
                        <p :style="{ margin: 0, fontSize: '12px', fontWeight: 500, color: 'var(--danger)' }">
                            {{ t('upload.filesRejected', rejectedFiles.length) }}
                        </p>
                        <ul :style="{ margin: '6px 0 0', padding: 0, listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '3px' }">
                            <li
                                v-for="(file, index) in rejectedFiles"
                                :key="index"
                                :style="{ fontSize: '11px', color: 'var(--fg-dim)' }"
                            >
                                <span :style="{ fontWeight: 500, color: 'var(--fg)' }">{{ file.name }}</span>
                                <span :style="{ color: 'var(--fg-mute)' }"> ({{ formatSize(file.size) }}) — {{ file.reason }}</span>
                            </li>
                        </ul>
                    </div>
                    <button
                        type="button"
                        :aria-label="t('common.close')"
                        :style="{ background: 'transparent', border: 'none', color: 'var(--danger)', cursor: 'pointer', padding: '2px', flexShrink: 0 }"
                        @click.stop="rejectedFiles = []"
                    >
                        <X :style="{ width: '14px', height: '14px' }" />
                    </button>
                </div>
            </div>
        </div>
    </CommandDialog>
</template>

<style scoped>
@keyframes cmdSpin {
    to { transform: rotate(360deg); }
}
@keyframes cmdBounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-3px); }
}
</style>
