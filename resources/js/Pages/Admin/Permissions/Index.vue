<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';
import { FilterMatchMode } from '@primevue/core/api';

defineOptions({ layout: AdminLayout });

interface Permission { id: number; name: string; guard_name: string; roles_count: number }
defineProps<{ permissions: Permission[] }>();

const form = useForm({ name: '' });
const confirm = useConfirm();
const filters = ref({ global: { value: null as string | null, matchMode: FilterMatchMode.CONTAINS } });

function create() {
    form.post('/admin/permissions', { onSuccess: () => form.reset() });
}

function destroy(p: Permission) {
    confirm.require({
        message: `Delete permission "${p.name}"?`,
        header: 'Confirm delete',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(`/admin/permissions/${p.id}`),
    });
}
</script>

<template>
    <Head title="Permissions · Admin" />
    <ConfirmDialog />
    <h1 class="text-2xl font-semibold mb-6">Permissions</h1>

    <form @submit.prevent="create" class="flex gap-2 mb-6">
        <InputText v-model="form.name" placeholder="new permission name" class="w-80" />
        <Button type="submit" label="Add" icon="pi pi-plus" :loading="form.processing" />
        <p v-if="form.errors.name" class="text-xs text-red-500 self-center">{{ form.errors.name }}</p>
    </form>

    <DataTable :value="permissions" stripedRows removableSort scrollable
               v-model:filters="filters" :globalFilterFields="['name', 'guard_name']"
               class="bg-white dark:bg-dark-900 rounded-xl overflow-hidden">
        <template #header>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ permissions.length }} permissions</span>
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="filters['global'].value" placeholder="Filter permissions" />
                </IconField>
            </div>
        </template>
        <Column field="id" header="ID" style="width: 5rem" sortable />
        <Column field="name" header="Name" sortable />
        <Column field="guard_name" header="Guard" style="width: 8rem" sortable />
        <Column field="roles_count" header="Roles" style="width: 6rem" sortable />
        <Column header="Actions" style="width: 10rem">
            <template #body="{ data }">
                <Button label="Delete" icon="pi pi-trash" size="small" severity="danger" @click="destroy(data)" />
            </template>
        </Column>
    </DataTable>
</template>
