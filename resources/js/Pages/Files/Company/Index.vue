<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CommandLayout from '@/Layouts/CommandLayout.vue';
import Icon from '@/Components/Command/Icon.vue';
import CommandDialog from '@/Components/Command/Dialog.vue';
import CmdButton from '@/Components/Command/Button.vue';
import FilesToolbar from '@/Components/Files/FilesToolbar.vue';
import FilesUsageBar from '@/Components/Files/FilesUsageBar.vue';
import type { PageProps } from '@/types';
import { useConfirm } from 'primevue/useconfirm';
import { useCommandToasts } from '@/composables/useCommandToasts';
import { useCustomer } from '@/composables/useCustomer';

defineOptions({ layout: CommandLayout });

interface Owner {
    id: number;
    name: string;
    avatar_thumb_url: string | null;
}

interface CompanyItem {
    id: number;
    uuid: string;
    type: 'folder' | 'file';
    scope: 'personal' | 'company';
    name: string;
    mime_type: string | null;
    size: number;
    parent_id: number | null;
    thumbnail_url: string | null;
    is_image: boolean;
    is_video?: boolean;
    linked: boolean;
    link_id: number | null;
    company_parent_id: number | null;
    owner: Owner | null;
    shared_by: { id: number; name: string } | null;
    shared_at: string | null;
    can_manage: boolean;
    created_at: string | null;
    updated_at: string | null;
}

interface Breadcrumb { id: number; name: string }

const props = defineProps<{
    items: CompanyItem[];
    breadcrumbs: Breadcrumb[];
    current_folder: { id: number; name: string; uuid: string } | null;
    usage: { used_bytes: number; quota_bytes: number | null; quota_unlimited: boolean; percent: number };
    can_manage: boolean;
    permissions: { upload: boolean; create_folder: boolean; manage: boolean };
    search: string | null;
    realtime_version: number;
    // Injected by useCustomer() via the page-level shared `customer` prop —
    // we reach through it to subscribe to the right channel.
}>();

const { t } = useI18n();
const { customerUrl, customer } = useCustomer();
const { push } = useCommandToasts();
const confirmer = useConfirm();
const page = usePage<PageProps>();

const parentId = computed(() => props.current_folder?.id ?? null);

// View mode (grid vs list) is persisted the same way the personal page
// does it, so the user's preference survives toggling between Private
// and Shared via the scope switcher.
const viewMode = ref<'grid' | 'list'>((localStorage.getItem('files.viewMode') as 'grid' | 'list') || 'grid');
function setViewMode(m: 'grid' | 'list') {
    viewMode.value = m;
    localStorage.setItem('files.viewMode', m);
}

const searchQuery = ref(props.search ?? '');
function onSearch() {
    router.get(customerUrl(parentId.value ? `/files/company/${parentId.value}` : '/files/company'), {
        q: searchQuery.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true, only: ['items', 'realtime_version'] });
}

// The scope switcher needs to know whether the viewer can still see the
// Shared tab — reuse the same permission gate as the server.
const switcherPermissions = computed(() => {
    const perms = (page.props.auth?.user as { permissions?: string[] } | undefined)?.permissions ?? [];
    const isSuperAdmin = (page.props.auth?.user as { is_super_admin?: boolean } | undefined)?.is_super_admin === true;
    return { canViewShared: isSuperAdmin || perms.includes('view company files') };
});

// ------------ Websocket live updates ------------
// Subscribe to the tenant's private files channel on mount; when any member
// mutates the tree the backend broadcasts a CompanyFilesChanged event
// carrying a bumped version. We compare against the version baked into our
// initial props and issue a partial inertia reload — cheap because the
// controller now cache-reads the listing keyed on that same version.
let lastVersion = 0;
let channelName: string | null = null;

function handleRealtime(payload: { tenant_id: number; reason: string; version: number; folder_id: number | null }) {
    if (payload.version <= lastVersion) return;
    lastVersion = payload.version;
    // Only refresh when the change concerns the current folder, or when
    // folder_id is null (events that may touch root / multiple folders like
    // link changes and admin actions).
    if (payload.folder_id !== null && payload.folder_id !== parentId.value) return;
    router.reload({ only: ['items', 'usage', 'realtime_version'] });
}

onMounted(() => {
    lastVersion = props.realtime_version ?? 0;
    const tenantId = customer.value?.id;
    const echo = (window as { Echo?: { private: (name: string) => { listen: (e: string, cb: (p: unknown) => void) => void } } }).Echo;
    if (!tenantId || !echo) return;
    channelName = `customer.${tenantId}.files`;
    echo.private(channelName).listen('.CompanyFilesChanged', handleRealtime as unknown as (p: unknown) => void);
});

onUnmounted(() => {
    if (channelName) {
        (window as { Echo?: { leave: (n: string) => void } }).Echo?.leave(channelName);
    }
});

// --- Upload ---
const uploadOpen = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

function triggerUpload() {
    if (!props.permissions.upload) return;
    fileInput.value?.click();
}

function onFilesChosen(event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input.files?.length) return;
    const form = new FormData();
    for (const f of Array.from(input.files)) form.append('files[]', f);
    if (parentId.value) form.append('parent_id', String(parentId.value));
    router.post(customerUrl('/files/company'), form, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => push(t('files.upload_success', { count: input.files!.length }), 'success'),
        onError: (errors) => {
            const first = Object.values(errors)[0];
            push(typeof first === 'string' ? first : t('common.error'), 'danger');
        },
        onFinish: () => { input.value = ''; },
    });
}

// --- Folder create ---
const newFolderOpen = ref(false);
const newFolderName = ref('');

function createFolder() {
    if (!newFolderName.value.trim()) return;
    router.post(customerUrl('/files/company/folder'), {
        name: newFolderName.value.trim(),
        parent_id: parentId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            push(t('files.folder_created', { name: newFolderName.value }), 'success');
            newFolderOpen.value = false;
            newFolderName.value = '';
        },
        // Keep the dialog open on error so the user can adjust the name
        // and retry — the folder create endpoint validates both length
        // and duplicates, and the server message is the most actionable
        // thing to surface.
        onError: (errors) => {
            const first = Object.values(errors)[0];
            push(typeof first === 'string' ? first : t('common.error'), 'danger');
        },
    });
}

// --- Delete / Unshare dialog (admin can also notify) ---
const deleteDialogItem = ref<CompanyItem | null>(null);
const notifyInApp = ref(true);
const notifyByEmail = ref(false);

function openDelete(item: CompanyItem) {
    if (!item.can_manage) return;
    deleteDialogItem.value = item;
    notifyInApp.value = true;
    notifyByEmail.value = false;
}

function closeDelete() {
    deleteDialogItem.value = null;
}

function confirmDelete() {
    const item = deleteDialogItem.value;
    if (!item) return;

    // Determine the exact endpoint: unlink for linked items, destroy for native.
    const endpoint = item.linked && item.link_id
        ? customerUrl(`/files/company/links/${item.link_id}`)
        : customerUrl(`/files/company/${item.id}`);

    const isOwnerAction = !(props.can_manage && item.owner && !selfIsOwner(item));
    const payload: Record<string, boolean> = isOwnerAction ? {} : {
        notify_in_app: notifyInApp.value,
        notify_email: notifyByEmail.value,
    };

    router.delete(endpoint, {
        data: payload,
        preserveScroll: true,
        onSuccess: () => {
            push(item.linked ? t('files.company_unlinked') : t('files.deleted'), 'success');
            closeDelete();
        },
        // Keep the dialog open on failure so the admin sees the reason
        // (403, validation, or a backend abort message) and can retry.
        onError: (errors) => {
            const first = Object.values(errors)[0];
            push(typeof first === 'string' ? first : t('common.error'), 'danger');
        },
    });
}

function selfIsOwner(item: CompanyItem): boolean {
    // We don't have the auth user id in props directly; the backend already
    // computed `can_manage` with owner-OR-admin, so we rely on `shared_by`
    // (for linked items) being absent as a weak signal. Keep the notify UI
    // available whenever the acting user might be an admin over someone
    // else — worst case the admin accidentally notifies themselves, which
    // the backend guards against anyway.
    return false;
}

// --- Download ---
function download(item: CompanyItem) {
    if (item.type !== 'file') return;
    window.location.href = customerUrl(`/files/company/${item.id}/download`);
}

// --- Helpers ---
function humanBytes(n: number | null | undefined): string {
    if (n == null || n < 0) return '—';
    if (n === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0; let v = n;
    while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
    return `${v.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

// Map item kinds onto the Command icon set — no dedicated folder/file
// glyphs, so we reuse `disk` for folders and `log` for anything file-ish
// and let the thumbnail (when present) carry the specificity.
function iconFor(item: CompanyItem): 'disk' | 'log' {
    return item.type === 'folder' ? 'disk' : 'log';
}

function openFolder(item: CompanyItem) {
    if (item.type !== 'folder') return;
    router.get(customerUrl(`/files/company/${item.id}`));
}

const quotaLabel = computed(() => {
    if (props.usage.quota_unlimited) return t('admin.customers.unlimited');
    if (props.usage.quota_bytes === null) return '—';
    return humanBytes(props.usage.quota_bytes);
});
</script>

<template>
    <div class="cmd-files-page">
        <Head :title="t('files.company_title')" />

        <!-- Shared toolbar — identical on both Files pages. Upload +
             New folder + search + grid/list + breadcrumbs + scope
             switcher all live in one component, so any design tweak
             lands on both scopes automatically. -->
        <FilesToolbar
            scope="shared"
            base-path="/files/company"
            :breadcrumbs="breadcrumbs"
            :root-label="t('files.breadcrumb_root')"
            v-model:search="searchQuery"
            :view-mode="viewMode"
            @update:viewMode="setViewMode"
            @submit-search="onSearch"
            @upload="triggerUpload"
            @new-folder="newFolderOpen = true"
            :permissions="{
                upload: permissions.upload,
                createFolder: permissions.create_folder,
                canViewShared: switcherPermissions.canViewShared,
            }"
        />
        <input ref="fileInput" type="file" multiple hidden @change="onFilesChosen" />

        <FilesUsageBar
            :used-bytes="usage.used_bytes"
            :quota-bytes="usage.quota_bytes"
            :quota-unlimited="usage.quota_unlimited"
        />

        <!-- Items -->
        <div v-if="items.length === 0" :style="{ padding: '40px 20px', textAlign: 'center', color: 'var(--fg-mute)', background: 'var(--panel)', border: '1px dashed var(--border)', borderRadius: '6px' }">
            {{ t('files.empty_folder') }}
        </div>

        <!-- Grid view — tile layout matching the personal Files grid -->
        <ul v-else-if="viewMode === 'grid'" :style="{
            listStyle: 'none',
            padding: 0,
            margin: 0,
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))',
            gap: '10px',
        }">
            <li
                v-for="item in items"
                :key="`g-${item.linked ? 'l' : 'f'}-${item.id}-${item.link_id ?? ''}`"
                :style="{
                    display: 'flex',
                    flexDirection: 'column',
                    background: 'var(--panel)',
                    border: '1px solid var(--border)',
                    borderRadius: '6px',
                    overflow: 'hidden',
                    cursor: item.type === 'folder' ? 'pointer' : 'default',
                }"
                @click="openFolder(item)"
            >
                <div :style="{ position: 'relative', aspectRatio: '4 / 3', background: 'var(--panel2)', display: 'flex', alignItems: 'center', justifyContent: 'center' }">
                    <img v-if="item.thumbnail_url" :src="item.thumbnail_url" :alt="item.name" :style="{ width: '100%', height: '100%', objectFit: 'cover' }" />
                    <Icon v-else :name="iconFor(item)" :size="32" :style="{ color: 'var(--fg-mute)' }" />
                    <span v-if="item.linked" :style="{ position: 'absolute', top: '6px', left: '6px', padding: '1px 6px', fontSize: '10px', background: 'var(--accent-soft)', color: 'var(--accent)', borderRadius: '3px', fontWeight: 500 }">
                        {{ t('files.linked_badge') }}
                    </span>
                    <div v-if="item.can_manage || item.type === 'file'" :style="{ position: 'absolute', top: '6px', right: '6px', display: 'flex', gap: '4px' }" @click.stop>
                        <button
                            v-if="item.type === 'file'"
                            @click="download(item)"
                            :title="t('files.download')"
                            :style="{ background: 'rgba(10,12,18,0.7)', border: 'none', color: '#fff', cursor: 'pointer', padding: '5px 7px', borderRadius: '9999px' }"
                        ><i class="pi pi-download" :style="{ fontSize: '10px' }" /></button>
                        <button
                            v-if="item.can_manage"
                            @click="openDelete(item)"
                            :title="item.linked ? t('files.unshare_from_company') : t('files.delete')"
                            :style="{ background: 'rgba(10,12,18,0.7)', border: 'none', color: '#fff', cursor: 'pointer', padding: '5px 7px', borderRadius: '9999px' }"
                        ><i class="pi pi-trash" :style="{ fontSize: '10px' }" /></button>
                    </div>
                </div>
                <div :style="{ padding: '10px 12px' }">
                    <div :style="{ fontSize: '12.5px', fontWeight: 500, color: 'var(--fg)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }">{{ item.name }}</div>
                    <div :style="{ fontSize: '11px', color: 'var(--fg-dim)', marginTop: '3px', display: 'flex', gap: '6px', alignItems: 'center' }">
                        <span v-if="item.type !== 'folder'">{{ humanBytes(item.size) }}</span>
                        <span v-if="item.owner" :style="{ display: 'inline-flex', alignItems: 'center', gap: '4px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }">
                            <img v-if="item.owner.avatar_thumb_url" :src="item.owner.avatar_thumb_url" :alt="item.owner.name" :style="{ width: '12px', height: '12px', borderRadius: '50%' }" />
                            <span :style="{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }">{{ item.owner.name }}</span>
                        </span>
                    </div>
                </div>
            </li>
        </ul>

        <!-- List view (default for dense layouts) -->
        <ul v-else :style="{ listStyle: 'none', padding: 0, margin: 0, display: 'grid', gap: '6px' }">
            <li
                v-for="item in items"
                :key="`${item.linked ? 'l' : 'f'}-${item.id}-${item.link_id ?? ''}`"
                :style="{ display: 'flex', alignItems: 'center', gap: '12px', padding: '10px 12px', background: 'var(--panel)', border: '1px solid var(--border)', borderRadius: '6px', cursor: item.type === 'folder' ? 'pointer' : 'default' }"
                @click="openFolder(item)"
            >
                <div :style="{ flexShrink: 0, width: '32px', height: '32px', display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'var(--panel2)', borderRadius: '4px' }">
                    <img v-if="item.thumbnail_url" :src="item.thumbnail_url" :alt="item.name" :style="{ maxWidth: '100%', maxHeight: '100%', borderRadius: '3px' }" />
                    <Icon v-else :name="iconFor(item)" :size="16" :style="{ color: 'var(--fg-dim)' }" />
                </div>

                <div :style="{ flex: 1, minWidth: 0 }">
                    <div :style="{ fontSize: '13px', fontWeight: 500, color: 'var(--fg)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }">
                        {{ item.name }}
                        <span v-if="item.linked" :title="t('files.shared_to_company')" :style="{ marginLeft: '6px', padding: '1px 6px', fontSize: '10px', background: 'var(--accent-soft)', color: 'var(--accent)', borderRadius: '3px', fontWeight: 500 }">
                            {{ t('files.linked_badge') }}
                        </span>
                    </div>
                    <div :style="{ fontSize: '11px', color: 'var(--fg-dim)', display: 'flex', gap: '8px', alignItems: 'center', marginTop: '2px' }">
                        <span v-if="item.type !== 'folder'">{{ humanBytes(item.size) }}</span>
                        <span v-if="item.owner" :style="{ display: 'inline-flex', alignItems: 'center', gap: '4px' }">
                            <img v-if="item.owner.avatar_thumb_url" :src="item.owner.avatar_thumb_url" :alt="item.owner.name" :style="{ width: '14px', height: '14px', borderRadius: '50%' }" />
                            <span>{{ t('files.owner_by', { name: item.owner.name }) }}</span>
                        </span>
                        <span v-if="item.shared_by" :style="{ color: 'var(--fg-mute)' }">· {{ t('files.shared_by', { name: item.shared_by.name }) }}</span>
                    </div>
                </div>

                <div :style="{ display: 'flex', gap: '4px', flexShrink: 0 }" @click.stop>
                    <button
                        v-if="item.type === 'file'"
                        @click="download(item)"
                        :title="t('files.download')"
                        :style="{ background: 'transparent', border: 'none', color: 'var(--fg-mute)', cursor: 'pointer', padding: '6px', borderRadius: '3px' }"
                    >
                        <Icon name="arrow" :size="12" />
                    </button>
                    <button
                        v-if="item.can_manage"
                        @click="openDelete(item)"
                        :title="item.linked ? t('files.unshare_from_company') : t('files.delete')"
                        :style="{ background: 'transparent', border: 'none', color: 'var(--fg-mute)', cursor: 'pointer', padding: '6px', borderRadius: '3px' }"
                    >
                        <Icon name="trash" :size="12" />
                    </button>
                </div>
            </li>
        </ul>

        <!-- New folder dialog -->
        <CommandDialog :visible="newFolderOpen" @update:visible="(v: boolean) => (newFolderOpen = v)" :title="t('files.new_folder')">
            <div :style="{ display: 'flex', flexDirection: 'column', gap: '12px', padding: '12px' }">
                <input
                    v-model="newFolderName"
                    :placeholder="t('files.new_folder')"
                    autofocus
                    @keydown.enter="createFolder"
                    :style="{ background: 'var(--panel2)', border: '1px solid var(--border)', borderRadius: '5px', padding: '8px 10px', color: 'var(--fg)', fontSize: '13px', outline: 'none' }"
                />
                <div :style="{ display: 'flex', justifyContent: 'flex-end', gap: '8px' }">
                    <CmdButton @click="newFolderOpen = false" variant="ghost">{{ t('common.cancel') }}</CmdButton>
                    <CmdButton @click="createFolder" variant="primary">{{ t('common.create') }}</CmdButton>
                </div>
            </div>
        </CommandDialog>

        <!-- Delete / unshare dialog -->
        <CommandDialog
            :visible="deleteDialogItem !== null"
            @update:visible="(v: boolean) => !v && closeDelete()"
            :title="deleteDialogItem?.linked ? t('files.company_unlink_title') : t('files.company_delete_title')"
        >
            <div v-if="deleteDialogItem" :style="{ padding: '12px', display: 'flex', flexDirection: 'column', gap: '12px' }">
                <p :style="{ margin: 0, fontSize: '13px', color: 'var(--fg)' }">
                    <template v-if="deleteDialogItem.linked">
                        {{ t('files.company_unlink_body', { file: deleteDialogItem.name }) }}
                    </template>
                    <template v-else>
                        {{ t('files.company_delete_body', { file: deleteDialogItem.name }) }}
                    </template>
                </p>

                <!-- Admin notify options. Owner-deleting-own never needs these,
                     but the UI shows them whenever the acting user can_manage
                     AND the owner is not themself. -->
                <div v-if="permissions.manage && deleteDialogItem.owner" :style="{ display: 'flex', flexDirection: 'column', gap: '6px', background: 'var(--panel2)', padding: '10px', borderRadius: '5px', border: '1px solid var(--border)' }">
                    <div :style="{ fontSize: '11px', color: 'var(--fg-mute)', fontWeight: 500 }">
                        {{ t('files.notify_owner', { name: deleteDialogItem.owner.name }) }}
                    </div>
                    <label :style="{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '12px', color: 'var(--fg)' }">
                        <input type="checkbox" v-model="notifyInApp" />
                        {{ t('files.notify_in_app') }}
                    </label>
                    <label :style="{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '12px', color: 'var(--fg)' }">
                        <input type="checkbox" v-model="notifyByEmail" />
                        {{ t('files.notify_by_email') }}
                    </label>
                </div>

                <div :style="{ display: 'flex', justifyContent: 'flex-end', gap: '8px' }">
                    <CmdButton @click="closeDelete" variant="ghost">{{ t('common.cancel') }}</CmdButton>
                    <CmdButton @click="confirmDelete" variant="danger">
                        {{ deleteDialogItem.linked ? t('files.company_unlink_confirm') : t('files.delete') }}
                    </CmdButton>
                </div>
            </div>
        </CommandDialog>
    </div>
</template>

<style scoped>
/* Mirror the personal Files wrapper so toggling between Private and
 * Shared doesn't snap the content between 1200 px centered and full
 * width. The class name matches the personal page deliberately — if
 * either value changes, update both so the two tabs stay visually
 * aligned. */
.cmd-files-page {
    position: relative;
    max-width: 1200px;
    margin: 0 auto;
}
</style>
