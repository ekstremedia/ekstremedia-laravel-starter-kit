<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';

defineOptions({ layout: AdminLayout });

interface Role { id: number; name: string; permissions: string[]; users_count: number }
defineProps<{ roles: Role[] }>();

const confirm = useConfirm();

function destroy(r: Role) {
    confirm.require({
        message: `Delete role "${r.name}"?`,
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
        <h1 class="text-2xl font-semibold">Roles</h1>
        <Link href="/admin/roles/create"><Button label="New role" icon="pi pi-plus" /></Link>
    </div>

    <DataTable :value="roles" stripedRows class="bg-white dark:bg-dark-900 rounded-xl overflow-hidden">
        <Column field="id" header="ID" style="width: 5rem" />
        <Column field="name" header="Name" />
        <Column header="Permissions">
            <template #body="{ data }">
                <Tag v-for="p in data.permissions" :key="p" :value="p" severity="secondary" class="mr-1 mb-1" />
            </template>
        </Column>
        <Column field="users_count" header="Users" style="width: 6rem" />
        <Column header="Actions" style="width: 14rem">
            <template #body="{ data }">
                <Link :href="`/admin/roles/${data.id}/edit`"><Button label="Edit" icon="pi pi-pencil" size="small" severity="secondary" class="mr-2" /></Link>
                <Button label="Delete" icon="pi pi-trash" size="small" severity="danger" @click="destroy(data)" />
            </template>
        </Column>
    </DataTable>
</template>
