<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Menu from 'primevue/menu';

interface FileItemLite {
    id: number;
    type: 'folder' | 'file';
}

const props = defineProps<{
    item: FileItemLite;
    downloadUrl?: string;
    variant?: 'overlay' | 'inline';
}>();

const emit = defineEmits<{
    open: [];
    rename: [];
    share: [];
    download: [];
    delete: [];
}>();

const { t } = useI18n();
const menuRef = ref<InstanceType<typeof Menu> | null>(null);

const items = computed(() => {
    const out: Array<Record<string, unknown>> = [
        { label: t('files.open'), icon: 'pi pi-external-link', command: () => emit('open') },
        { label: t('files.rename'), icon: 'pi pi-pencil', command: () => emit('rename') },
        { label: t('files.share'), icon: 'pi pi-share-alt', command: () => emit('share') },
    ];

    if (props.item.type === 'file') {
        out.push({
            label: t('files.download'),
            icon: 'pi pi-download',
            url: props.downloadUrl,
            command: () => emit('download'),
        });
    }

    out.push({ separator: true });
    out.push({
        label: t('files.delete'),
        icon: 'pi pi-trash',
        // PrimeVue Menu styles items individually — inject a class to tint the row red.
        class: 'file-action-danger',
        command: () => emit('delete'),
    });

    return out;
});

function toggle(event: MouseEvent) {
    event.stopPropagation();
    menuRef.value?.toggle(event);
}

const triggerClass = computed(() => props.variant === 'inline'
    ? 'rounded p-1.5 text-slate-500 transition hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-dark-800'
    : 'rounded-full bg-slate-900/70 p-1.5 text-white transition hover:bg-slate-900');
</script>

<template>
    <button
        type="button"
        :class="triggerClass"
        :title="t('common.actions')"
        :aria-label="t('common.actions')"
        @click.stop="toggle"
    >
        <i class="pi pi-ellipsis-v" />
    </button>
    <Menu ref="menuRef" :model="items" :popup="true" />
</template>

<style>
.file-action-danger .p-menuitem-link,
.file-action-danger .p-menuitem-icon,
.file-action-danger .p-menuitem-text {
    color: rgb(244, 63, 94);
}
.file-action-danger .p-menuitem-link:hover {
    background-color: rgba(244, 63, 94, 0.12);
}
</style>
