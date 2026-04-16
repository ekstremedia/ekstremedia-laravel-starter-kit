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

defineOptions({ layout: AdminLayout });

interface CustomerRow {
    id: number;
    slug: string;
    name: string;
    status: 'active' | 'suspended';
    users_count: number;
    created_at: string;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

defineProps<{ customers: Paginated<CustomerRow> }>();

const confirm = useConfirm();
const filters = ref({ global: { value: null as string | null, matchMode: FilterMatchMode.CONTAINS } });

function destroy(customer: CustomerRow) {
    confirm.require({
        message: `Delete "${customer.name}"? This will drop the tenant${customer.id} Postgres schema and all its data.`,
        header: 'Confirm delete',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(`/admin/customers/${customer.id}`),
    });
}

function statusSeverity(s: string) {
    return s === 'active' ? 'success' : 'warn';
}
</script>

<template>
    <Head title="Customers · Admin" />
    <ConfirmDialog />
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">Customers</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Each customer is an isolated Postgres schema. Users must be members to enter one.
            </p>
        </div>
        <Link href="/admin/customers/create"><Button label="New customer" icon="pi pi-plus" /></Link>
    </div>

    <DataTable :value="customers.data" stripedRows removableSort scrollable
               v-model:filters="filters" :globalFilterFields="['name', 'slug']"
               class="bg-white dark:bg-dark-900 rounded-xl overflow-hidden">
        <template #header>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ customers.total }} customers</span>
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="filters['global'].value" placeholder="Filter by name or slug" />
                </IconField>
            </div>
        </template>
        <Column field="id" header="ID" style="width: 5rem" sortable />
        <Column field="name" header="Name" sortable />
        <Column field="slug" header="Slug" sortable>
            <template #body="{ data }">
                <code class="text-xs bg-gray-100 dark:bg-dark-800 px-1.5 py-0.5 rounded">/c/{{ data.slug }}</code>
            </template>
        </Column>
        <Column field="status" header="Status" sortable style="width: 8rem">
            <template #body="{ data }">
                <Tag :value="data.status" :severity="statusSeverity(data.status)" />
            </template>
        </Column>
        <Column field="users_count" header="Members" sortable style="width: 7rem" />
        <Column header="Actions" style="width: 14rem">
            <template #body="{ data }">
                <Link :href="`/admin/customers/${data.id}/edit`">
                    <Button label="Edit" icon="pi pi-pencil" size="small" severity="secondary" class="mr-2" />
                </Link>
                <Button label="Delete" icon="pi pi-trash" size="small" severity="danger" @click="destroy(data)" />
            </template>
        </Column>
    </DataTable>
</template>
