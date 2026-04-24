<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Menu from 'primevue/menu';

interface FileItemLite {
    id: number;
    type: 'folder' | 'file';
    shared_to_company?: boolean;
}

const props = defineProps<{
    item: FileItemLite;
    downloadUrl?: string;
    variant?: 'overlay' | 'inline';
    // Feature flag: whether the surrounding page supports share-to-company.
    // Enabled only when the tenant has company_files_enabled and the user
    // has the `share files to company` permission — the parent resolves both.
    canShareToCompany?: boolean;
}>();

const emit = defineEmits<{
    open: [];
    rename: [];
    share: [];
    download: [];
    delete: [];
    shareToCompany: [];
    unshareFromCompany: [];
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

    // Share-to-company works on both files (one link) and folders (recursive
    // mirror into a matching company folder tree). Files surface a "shared"
    // badge when already linked; folders always show the share action —
    // sharing again is idempotent and picks up any files added since.
    if (props.canShareToCompany) {
        if (props.item.type === 'file' && props.item.shared_to_company) {
            out.push({
                label: t('files.unshare_from_company'),
                icon: 'pi pi-link',
                command: () => emit('unshareFromCompany'),
            });
        } else {
            out.push({
                label: t('files.share_to_company'),
                icon: 'pi pi-users',
                command: () => emit('shareToCompany'),
            });
        }
        if (props.item.type === 'folder') {
            out.push({
                label: t('files.unshare_from_company'),
                icon: 'pi pi-link',
                command: () => emit('unshareFromCompany'),
            });
        }
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

const triggerStyle = computed(() => props.variant === 'inline'
    ? {
        background: 'transparent',
        border: '1px solid transparent',
        color: 'var(--fg-mute)',
        borderRadius: '4px',
        padding: '4px 6px',
        cursor: 'pointer',
        fontFamily: 'inherit',
    }
    : {
        background: 'rgba(10,12,18,0.7)',
        border: 'none',
        color: '#fff',
        borderRadius: '9999px',
        padding: '5px 7px',
        cursor: 'pointer',
        fontFamily: 'inherit',
    });
</script>

<template>
    <span :style="{ display: 'inline-flex' }">
        <button
            type="button"
            class="cmd-file-menu-trigger"
            :style="triggerStyle"
            :title="t('common.actions')"
            :aria-label="t('common.actions')"
            @click.stop="toggle"
        >
            <i class="pi pi-ellipsis-v" />
        </button>
        <Menu ref="menuRef" :model="items" :popup="true" />
    </span>
</template>

<style>
.cmd-file-menu-trigger:hover {
    background: var(--row-hover) !important;
    color: var(--fg) !important;
}
.file-action-danger .p-menuitem-link,
.file-action-danger .p-menuitem-icon,
.file-action-danger .p-menuitem-text {
    color: var(--danger);
}
.file-action-danger .p-menuitem-link:hover {
    background-color: rgba(255, 138, 138, 0.12);
}
</style>
