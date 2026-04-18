<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatDateTime } from '@/composables/useDateTime';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

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
            <h1 class="text-2xl font-semibold">{{ t('admin.backups.title') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ t('admin.backups.schedule') }}
                {{ t('admin.backups.stored_to') }} <Tag v-for="d in disks" :key="d" :value="d" severity="info" class="ml-1" />
            </p>
        </div>
        <div class="flex gap-2">
            <Button :label="t('admin.backups.run_now')" icon="pi pi-play" @click="runBackup" />
            <Button :label="t('admin.backups.clean')" icon="pi pi-trash" severity="secondary" @click="runClean" />
        </div>
    </div>

    <DataTable :value="backups" stripedRows removableSort scrollable
               class="bg-white dark:bg-dark-900 rounded-xl overflow-hidden">
        <Column field="date" :header="t('admin.backups.date')" style="width: 18rem" sortable>
            <template #body="{ data }">{{ formatDateTime(data.date) }}</template>
        </Column>
        <Column field="disk" :header="t('admin.backups.disk')" style="width: 10rem" sortable>
            <template #body="{ data }"><Tag :value="data.disk" severity="info" /></template>
        </Column>
        <Column field="path" :header="t('admin.backups.file')" sortable />
        <Column field="size" :header="t('admin.backups.size')" style="width: 10rem" sortable>
            <template #body="{ data }">{{ humanSize(data.size) }}</template>
        </Column>
    </DataTable>

    <p v-if="backups.length === 0" class="text-sm text-gray-500 mt-4">
        {{ t('admin.backups.empty') }}
    </p>
</template>
