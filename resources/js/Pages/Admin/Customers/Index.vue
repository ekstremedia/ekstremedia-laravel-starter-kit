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
const searchValue = ref<string>('');

function onSearchInput(v: string) {
    searchValue.value = v;
    filters.value.global.value = v || null;
}

function destroy(customer: CustomerRow) {
    confirm.require({
        message: t('admin.customers.confirm_delete', { name: customer.name }),
        header: t('common.confirm'),
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

    <PageHeader :title="t('admin.customers.title')" :description="t('admin.customers.desc')">
        <template #actions>
            <Link href="/admin/customers/create">
                <Button :label="t('admin.customers.new_customer')" icon="pi pi-plus" />
            </Link>
        </template>
    </PageHeader>

    <DataTableShell
        :count="customers.total"
        :count-label="t('admin.customers.title').toLowerCase()"
        :search-placeholder="t('admin.customers.filter')"
        :search-value="searchValue"
        @update:search-value="onSearchInput"
    >
        <DataTable
            :value="customers.data"
            stripedRows
            removableSort
            scrollable
            v-model:filters="filters"
            :globalFilterFields="['name', 'slug']"
            class="border-0"
        >
            <Column field="id" :header="t('common.id')" style="width: 5rem" sortable />
            <Column field="name" :header="t('common.name')" sortable>
                <template #body="{ data }">
                    <Link
                        :href="`/admin/customers/${data.id}/edit`"
                        class="font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline underline-offset-2"
                    >
                        {{ data.name }}
                    </Link>
                </template>
            </Column>
            <Column field="slug" :header="t('admin.customers.slug')" sortable>
                <template #body="{ data }">
                    <code class="text-xs bg-gray-100 dark:bg-dark-800 px-1.5 py-0.5 rounded">/c/{{ data.slug }}</code>
                </template>
            </Column>
            <Column field="status" :header="t('common.status')" sortable style="width: 8rem">
                <template #body="{ data }">
                    <Tag :value="data.status" :severity="statusSeverity(data.status)" />
                </template>
            </Column>
            <Column field="users_count" :header="t('admin.customers.members')" sortable style="width: 7rem" />
            <Column :header="t('common.actions')" style="width: 10rem">
                <template #body="{ data }">
                    <Link :href="`/admin/customers/${data.id}/edit`">
                        <Button icon="pi pi-pencil" size="small" severity="secondary" class="mr-2" :title="t('common.edit')" />
                    </Link>
                    <Button icon="pi pi-trash" size="small" severity="danger" :title="t('common.delete')" @click="destroy(data)" />
                </template>
            </Column>
        </DataTable>
    </DataTableShell>
</template>
