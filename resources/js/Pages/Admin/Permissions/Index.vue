<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTableShell from '@/Components/Admin/DataTableShell.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';
import { FilterMatchMode } from '@primevue/core/api';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface Permission { id: number; name: string; guard_name: string; roles_count: number }
defineProps<{ permissions: Permission[] }>();

const form = useForm({ name: '' });
const confirm = useConfirm();
const filters = ref({ global: { value: null as string | null, matchMode: FilterMatchMode.CONTAINS } });
const searchValue = ref<string>('');

function onSearchInput(v: string) {
    searchValue.value = v;
    filters.value.global.value = v || null;
}

function create() {
    form.post('/admin/permissions', { onSuccess: () => form.reset() });
}

function destroy(p: Permission) {
    confirm.require({
        message: t('admin.permissions.confirm_delete', { name: p.name }),
        header: t('common.confirm'),
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(`/admin/permissions/${p.id}`),
    });
}
</script>

<template>
    <Head title="Permissions · Admin" />
    <ConfirmDialog />

    <PageHeader :title="t('admin.permissions.title')" />

    <form @submit.prevent="create" class="flex flex-wrap items-start gap-2 mb-6">
        <InputText v-model="form.name" :placeholder="t('admin.permissions.new_permission')" class="w-80" />
        <Button type="submit" :label="t('common.add')" icon="pi pi-plus" :loading="form.processing" />
        <p v-if="form.errors.name" class="text-xs text-red-500 self-center">{{ form.errors.name }}</p>
    </form>

    <DataTableShell
        :count="permissions.length"
        :count-label="t('admin.permissions.title').toLowerCase()"
        :search-placeholder="t('admin.permissions.filter')"
        :search-value="searchValue"
        @update:search-value="onSearchInput"
    >
        <DataTable
            :value="permissions"
            stripedRows
            removableSort
            scrollable
            v-model:filters="filters"
            :globalFilterFields="['name', 'guard_name']"
            class="border-0"
        >
            <Column field="id" :header="t('common.id')" style="width: 5rem" sortable />
            <Column field="name" :header="t('common.name')" sortable>
                <template #body="{ data }">
                    <span class="font-medium text-gray-900 dark:text-white">{{ data.name }}</span>
                </template>
            </Column>
            <Column field="guard_name" :header="t('admin.permissions.guard')" style="width: 8rem" sortable />
            <Column field="roles_count" :header="t('admin.permissions.roles')" style="width: 6rem" sortable />
            <Column :header="t('common.actions')" style="width: 8rem">
                <template #body="{ data }">
                    <Button icon="pi pi-trash" size="small" severity="danger" :title="t('common.delete')" @click="destroy(data)" />
                </template>
            </Column>
        </DataTable>
    </DataTableShell>
</template>
