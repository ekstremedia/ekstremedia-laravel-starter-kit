<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';

defineOptions({ layout: AdminLayout });

interface Permission { id: number; name: string; guard_name: string; roles_count: number }
defineProps<{ permissions: Permission[] }>();

const form = useForm({ name: '' });
const confirm = useConfirm();

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

    <DataTable :value="permissions" stripedRows class="bg-white dark:bg-dark-900 rounded-xl overflow-hidden">
        <Column field="id" header="ID" style="width: 5rem" />
        <Column field="name" header="Name" />
        <Column field="guard_name" header="Guard" style="width: 8rem" />
        <Column field="roles_count" header="Roles" style="width: 6rem" />
        <Column header="Actions" style="width: 10rem">
            <template #body="{ data }">
                <Button label="Delete" icon="pi pi-trash" size="small" severity="danger" @click="destroy(data)" />
            </template>
        </Column>
    </DataTable>
</template>
