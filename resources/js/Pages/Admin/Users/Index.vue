<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';

defineOptions({ layout: AdminLayout });

interface UserRow { id: number; first_name: string; last_name: string; email: string; created_at: string; roles: { id: number; name: string }[] }
interface Props {
    users: { data: UserRow[]; links: any; meta?: any; current_page?: number; last_page?: number; total?: number };
    filters: { search: string };
}
const props = defineProps<Props>();

const search = ref(props.filters.search ?? '');
const confirm = useConfirm();

function doSearch() {
    router.get('/admin/users', { search: search.value }, { preserveState: true, replace: true });
}

function destroy(u: UserRow) {
    confirm.require({
        message: `Delete ${u.email}?`,
        header: 'Confirm delete',
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
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">Users</h1>
        <Link href="/admin/users/create">
            <Button label="New user" icon="pi pi-plus" />
        </Link>
    </div>

    <div class="mb-4 flex gap-2">
        <InputText v-model="search" placeholder="Search name or email" class="w-64" @keydown.enter="doSearch" />
        <Button label="Search" icon="pi pi-search" severity="secondary" @click="doSearch" />
    </div>

    <DataTable :value="users.data" stripedRows class="bg-white dark:bg-dark-900 rounded-xl overflow-hidden">
        <Column field="id" header="ID" style="width: 5rem" />
        <Column header="Name">
            <template #body="{ data }">{{ data.first_name }} {{ data.last_name }}</template>
        </Column>
        <Column field="email" header="Email" />
        <Column header="Roles">
            <template #body="{ data }">
                <Tag v-for="r in data.roles" :key="r.id" :value="r.name" class="mr-1" severity="info" />
            </template>
        </Column>
        <Column header="Actions" style="width: 16rem">
            <template #body="{ data }">
                <Link :href="`/admin/users/${data.id}`">
                    <Button icon="pi pi-eye" size="small" severity="secondary" class="mr-1" title="View" />
                </Link>
                <Link :href="`/admin/users/${data.id}/edit`">
                    <Button icon="pi pi-pencil" size="small" severity="secondary" class="mr-1" title="Edit" />
                </Link>
                <Button
                    v-if="canImpersonate(data)"
                    icon="pi pi-user-edit"
                    size="small"
                    severity="warn"
                    class="mr-1"
                    title="Log in as this user"
                    @click="impersonate(data)"
                />
                <Button icon="pi pi-trash" size="small" severity="danger" title="Delete" @click="destroy(data)" />
            </template>
        </Column>
    </DataTable>

    <div class="mt-4 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <template v-for="link in users.links" :key="link.label">
            <Link v-if="link.url" :href="link.url"
                  class="px-3 py-1 rounded border border-gray-200 dark:border-dark-700 hover:bg-gray-100 dark:hover:bg-dark-800"
                  :class="{ 'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700': link.active }"
                  v-html="link.label" />
            <span v-else class="px-3 py-1 text-gray-400" v-html="link.label"></span>
        </template>
    </div>
</template>
