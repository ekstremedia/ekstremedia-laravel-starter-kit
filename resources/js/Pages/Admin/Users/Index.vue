<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTableShell from '@/Components/Admin/DataTableShell.vue';
import PaginationLinks from '@/Components/Admin/PaginationLinks.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';

defineOptions({ layout: AdminLayout });

interface UserRow {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    created_at: string;
    avatar_thumb_url: string | null;
    roles: { id: number; name: string }[];
    storage_used_bytes: number;
    storage_quota_bytes: number | null;
}

function initials(u: UserRow) {
    return ((u.first_name?.[0] ?? '') + (u.last_name?.[0] ?? '')).toUpperCase();
}

function formatBytes(n: number): string {
    if (!n) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0; let v = n;
    while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
    return `${v.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function quotaLabel(u: UserRow): string {
    if (u.storage_quota_bytes === null) return t('admin.storage.quota_unlimited');
    if (u.storage_quota_bytes === 0) return t('admin.storage.quota_disabled');
    return formatBytes(u.storage_quota_bytes);
}

interface Props {
    users: { data: UserRow[]; links: any; meta?: any; current_page?: number; last_page?: number; total?: number };
    filters: { search: string; sort?: string; direction?: string };
}
const props = defineProps<Props>();

const { t } = useI18n();
const search = ref(props.filters.search ?? '');
const confirm = useConfirm();

const quotaDialogUser = ref<UserRow | null>(null);
const quotaPreset = ref<'unlimited' | 'disabled' | 'gb'>('gb');
const quotaGb = ref(1);

function doSearch() {
    router.get('/admin/users', { search: search.value }, { preserveState: true, replace: true });
}

function openQuotaDialog(u: UserRow) {
    quotaDialogUser.value = u;
    if (u.storage_quota_bytes === null) { quotaPreset.value = 'unlimited'; }
    else if (u.storage_quota_bytes === 0) { quotaPreset.value = 'disabled'; }
    else {
        quotaPreset.value = 'gb';
        quotaGb.value = Math.max(1, Math.round(u.storage_quota_bytes / (1024 * 1024 * 1024)));
    }
}

function saveQuota() {
    const u = quotaDialogUser.value;
    if (!u) return;
    let bytes: number | null;
    if (quotaPreset.value === 'unlimited') bytes = null;
    else if (quotaPreset.value === 'disabled') bytes = 0;
    else bytes = Math.max(0, quotaGb.value) * 1024 * 1024 * 1024;

    router.patch(`/admin/users/${u.id}/quota`, { storage_quota_bytes: bytes }, {
        preserveScroll: true,
        onSuccess: () => { quotaDialogUser.value = null; },
    });
}

function destroy(u: UserRow) {
    confirm.require({
        message: t('admin.users.confirm_delete', { email: u.email }),
        header: t('common.confirm'),
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(`/admin/users/${u.id}`),
    });
}

function impersonate(u: UserRow) {
    router.post(`/admin/users/${u.id}/impersonate`);
}

function canImpersonate(u: UserRow) {
    return ! (u.roles ?? []).some((r: any) => (typeof r === 'string' ? r : r.name) === 'Admin');
}
</script>

<template>
    <Head title="Users · Admin" />
    <ConfirmDialog />

    <PageHeader :title="t('admin.users.title')">
        <template #actions>
            <Link href="/admin/users/create">
                <Button :label="t('admin.users.new_user')" icon="pi pi-plus" />
            </Link>
        </template>
    </PageHeader>

    <DataTableShell
        :count="users.total ?? users.data.length"
        :count-label="t('admin.users.title').toLowerCase()"
        :search-placeholder="t('admin.users.search_placeholder')"
        v-model:search-value="search"
        @search-submit="doSearch"
    >
        <DataTable :value="users.data" stripedRows removableSort scrollable class="border-0">
            <Column field="id" :header="t('common.id')" style="width: 5rem" sortable />
            <Column header="" style="width: 4rem">
                <template #body="{ data }">
                    <Link :href="`/admin/users/${data.id}`" class="inline-block">
                        <img v-if="data.avatar_thumb_url" :src="data.avatar_thumb_url" :alt="`${data.first_name} ${data.last_name}`"
                             class="w-9 h-9 rounded-full object-cover ring-1 ring-indigo-500/20" />
                        <div v-else class="w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs font-semibold">
                            {{ initials(data) }}
                        </div>
                    </Link>
                </template>
            </Column>
            <Column :header="t('common.name')" field="first_name" sortable>
                <template #body="{ data }">
                    <Link
                        :href="`/admin/users/${data.id}`"
                        class="font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline underline-offset-2"
                    >
                        {{ data.first_name }} {{ data.last_name }}
                    </Link>
                </template>
            </Column>
            <Column field="email" :header="t('auth.email')" sortable />
            <Column :header="t('admin.roles.title')">
                <template #body="{ data }">
                    <Tag v-for="r in data.roles" :key="r.id" :value="r.name" class="mr-1" severity="info" />
                </template>
            </Column>
            <Column field="storage_used_bytes" :header="t('admin.storage.storage_used')" sortable style="width: 11rem">
                <template #body="{ data }">
                    <div class="flex flex-col text-xs">
                        <span class="font-medium">{{ formatBytes(data.storage_used_bytes ?? 0) }}</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ quotaLabel(data) }}</span>
                    </div>
                </template>
            </Column>
            <Column :header="t('common.actions')" style="width: 14rem">
                <template #body="{ data }">
                    <Link :href="`/admin/users/${data.id}/edit`">
                        <Button icon="pi pi-pencil" size="small" severity="secondary" class="mr-1" :title="t('common.edit')" />
                    </Link>
                    <Button icon="pi pi-database" size="small" severity="help" class="mr-1" :title="t('admin.storage.set_quota')" @click="openQuotaDialog(data)" />
                    <Button
                        v-if="canImpersonate(data)"
                        icon="pi pi-user-edit"
                        size="small"
                        severity="warn"
                        class="mr-1"
                        :title="t('admin.users.impersonate')"
                        @click="impersonate(data)"
                    />
                    <Button icon="pi pi-trash" size="small" severity="danger" :title="t('common.delete')" @click="destroy(data)" />
                </template>
            </Column>
        </DataTable>
    </DataTableShell>

    <!-- Quota dialog — simple overlay since PrimeVue Dialog adds another boot dep -->
    <div
        v-if="quotaDialogUser"
        class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="quota-dialog-title"
        tabindex="-1"
        @click.self="quotaDialogUser = null"
        @keydown.esc="quotaDialogUser = null"
    >
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-dark-900">
            <h2 id="quota-dialog-title" class="mb-4 text-lg font-semibold text-slate-800 dark:text-slate-100">
                {{ t('admin.storage.set_quota_for_user', { name: quotaDialogUser.first_name + ' ' + quotaDialogUser.last_name }) }}
            </h2>
            <div class="space-y-3 text-sm">
                <label class="flex items-center gap-2">
                    <input type="radio" v-model="quotaPreset" value="unlimited" />
                    <span>{{ t('admin.storage.quota_unlimited') }}</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" v-model="quotaPreset" value="disabled" />
                    <span>{{ t('admin.storage.quota_disabled') }}</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" v-model="quotaPreset" value="gb" />
                    <span class="flex-1">{{ t('admin.storage.quota_label_gb') }}</span>
                    <input
                        v-model.number="quotaGb"
                        type="number"
                        min="0"
                        class="w-24 rounded border border-slate-300 px-2 py-1 dark:border-dark-700 dark:bg-dark-800"
                        :disabled="quotaPreset !== 'gb'"
                    />
                </label>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <Button :label="t('common.cancel')" severity="secondary" @click="quotaDialogUser = null" />
                <Button :label="t('common.save')" @click="saveQuota" />
            </div>
        </div>
    </div>

    <PaginationLinks :links="users.links" />
</template>
