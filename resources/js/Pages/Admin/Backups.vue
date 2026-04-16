<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';

defineOptions({ layout: AdminLayout });

interface Backup { disk: string; path: string; size: number; date: string }
defineProps<{
    backups: Backup[];
    disks: string[];
    name: string;
}>();

function humanSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1024 ** 3) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
    return `${(bytes / 1024 ** 3).toFixed(2)} GB`;
}

function runBackup() {
    router.post('/admin/backups/run', {}, { preserveScroll: true });
}
function runClean() {
    router.post('/admin/backups/clean', {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Backups · Admin" />
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">Backups</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Scheduled daily at 02:00 · cleanup 01:30 · monitored 06:00.
                Stored to <Tag v-for="d in disks" :key="d" :value="d" severity="info" class="ml-1" />
            </p>
        </div>
        <div class="flex gap-2">
            <Button label="Run backup now" icon="pi pi-play" @click="runBackup" />
            <Button label="Clean old backups" icon="pi pi-trash" severity="secondary" @click="runClean" />
        </div>
    </div>

    <DataTable :value="backups" stripedRows removableSort scrollable
               class="bg-white dark:bg-dark-900 rounded-xl overflow-hidden">
        <Column field="date" header="Date" style="width: 18rem" sortable>
            <template #body="{ data }">{{ new Date(data.date).toLocaleString() }}</template>
        </Column>
        <Column field="disk" header="Disk" style="width: 10rem" sortable>
            <template #body="{ data }"><Tag :value="data.disk" severity="info" /></template>
        </Column>
        <Column field="path" header="File" sortable />
        <Column field="size" header="Size" style="width: 10rem" sortable>
            <template #body="{ data }">{{ humanSize(data.size) }}</template>
        </Column>
    </DataTable>

    <p v-if="backups.length === 0" class="text-sm text-gray-500 mt-4">
        No backups yet. Click "Run backup now" to create the first one, or wait for the daily schedule.
    </p>
</template>
