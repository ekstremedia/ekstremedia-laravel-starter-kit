<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTableShell from '@/Components/Admin/DataTableShell.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';
import { FilterMatchMode } from '@primevue/core/api';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface Role { id: number; name: string; permissions: string[]; users_count: number }
defineProps<{ roles: Role[] }>();

const confirm = useConfirm();
const filters = ref({ global: { value: null as string | null, matchMode: FilterMatchMode.CONTAINS } });
const searchValue = ref<string>('');

function onSearchInput(v: string) {
    searchValue.value = v;
    filters.value.global.value = v || null;
}

function destroy(r: Role) {
    confirm.require({
        message: t('admin.roles.confirm_delete', { name: r.name }),
        header: t('common.confirm'),
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(`/admin/roles/${r.id}`),
    });
}
</script>

<template>
    <Head title="Roles · Admin" />
    <ConfirmDialog />

    <PageHeader :title="t('admin.roles.title')">
        <template #actions>
            <Link href="/admin/roles/create">
                <Button :label="t('admin.roles.new_role')" icon="pi pi-plus" />
            </Link>
        </template>
    </PageHeader>

    <DataTableShell
        :count="roles.length"
        :count-label="t('admin.roles.title').toLowerCase()"
        :search-placeholder="t('admin.roles.filter')"
        :search-value="searchValue"
        @update:search-value="onSearchInput"
    >
        <DataTable
            :value="roles"
            stripedRows
            removableSort
            scrollable
            v-model:filters="filters"
            :globalFilterFields="['name', 'permissions']"
            class="border-0"
        >
            <Column field="id" :header="t('common.id')" style="width: 5rem" sortable />
            <Column field="name" :header="t('common.name')" sortable>
                <template #body="{ data }">
                    <Link
                        :href="`/admin/roles/${data.id}/edit`"
                        class="font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline underline-offset-2"
                    >
                        {{ data.name }}
                    </Link>
                </template>
            </Column>
            <Column :header="t('admin.roles.permissions')">
                <template #body="{ data }">
                    <Tag v-for="p in data.permissions" :key="p" :value="p" severity="secondary" class="mr-1 mb-1" />
                </template>
            </Column>
            <Column field="users_count" :header="t('admin.roles.users')" style="width: 6rem" sortable />
            <Column :header="t('common.actions')" style="width: 10rem">
                <template #body="{ data }">
                    <Link :href="`/admin/roles/${data.id}/edit`">
                        <Button icon="pi pi-pencil" size="small" severity="secondary" class="mr-2" :title="t('common.edit')" />
                    </Link>
                    <Button icon="pi pi-trash" size="small" severity="danger" :title="t('common.delete')" @click="destroy(data)" />
                </template>
            </Column>
        </DataTable>
    </DataTableShell>
</template>
