<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
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

function destroy(r: Role) {
    confirm.require({
        message: t('admin.roles.confirm_delete', { name: r.name }),
        header: 'Confirm delete',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(`/admin/roles/${r.id}`),
    });
}
</script>

<template>
    <Head title="Roles · Admin" />
    <ConfirmDialog />
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">{{ t('admin.roles.title') }}</h1>
        <Link href="/admin/roles/create"><Button :label="t('admin.roles.new_role')" icon="pi pi-plus" /></Link>
    </div>

    <DataTable :value="roles" stripedRows removableSort scrollable
               v-model:filters="filters" :globalFilterFields="['name', 'permissions']"
               class="bg-white dark:bg-dark-900 rounded-xl overflow-hidden">
        <template #header>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ roles.length }} roles</span>
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="filters['global'].value" :placeholder="t('admin.roles.filter')" />
                </IconField>
            </div>
        </template>
        <Column field="id" :header="t('common.id')" style="width: 5rem" sortable />
        <Column field="name" :header="t('common.name')" sortable />
        <Column :header="t('admin.roles.permissions')">
            <template #body="{ data }">
                <Tag v-for="p in data.permissions" :key="p" :value="p" severity="secondary" class="mr-1 mb-1" />
            </template>
        </Column>
        <Column field="users_count" :header="t('admin.roles.users')" style="width: 6rem" sortable />
        <Column :header="t('common.actions')" style="width: 14rem">
            <template #body="{ data }">
                <Link :href="`/admin/roles/${data.id}/edit`"><Button :label="t('common.edit')" icon="pi pi-pencil" size="small" severity="secondary" class="mr-2" /></Link>
                <Button :label="t('common.delete')" icon="pi pi-trash" size="small" severity="danger" @click="destroy(data)" />
            </template>
        </Column>
    </DataTable>
</template>
