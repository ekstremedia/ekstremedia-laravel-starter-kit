<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTableShell from '@/Components/Admin/DataTableShell.vue';
import { formatDateTime } from '@/composables/useDateTime';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface Backup { disk: string; path: string; size: number; date: string }
interface Summary { disk: string; count: number; used_bytes: number; newest_at: string | null; error?: boolean }
interface BackupConfig {
    includes: string[];
    excludes: string[];
    databases: string[];
    retention_daily: number;
    retention_daily_keep: number;
    retention_weekly_keep: number;
    retention_monthly_keep: number;
    retention_yearly_keep: number;
    max_storage_mb: number;
}

const props = defineProps<{
    backups: Backup[];
    disks: string[];
    name: string;
    summaries: Summary[];
    config: BackupConfig;
}>();

function humanSize(bytes: number | null | undefined): string {
    if (!bytes || bytes <= 0) return '0 B';
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

function downloadUrl(b: Backup): string {
    const params = new URLSearchParams({ disk: b.disk, path: b.path });
    return `/admin/backups/download?${params.toString()}`;
}

const infoOpen = ref(true);

const restoreTarget = ref<Backup | null>(null);
const restoreConfirmText = ref('');

const restoreFilename = computed(() =>
    restoreTarget.value ? restoreTarget.value.path.split('/').pop() ?? '' : '',
);

const restoreValid = computed(
    () => restoreConfirmText.value.trim() === restoreFilename.value,
);

function openRestore(b: Backup) {
    restoreTarget.value = b;
    restoreConfirmText.value = '';
}

function cancelRestore() {
    restoreTarget.value = null;
    restoreConfirmText.value = '';
}

function submitRestore() {
    if (!restoreTarget.value || !restoreValid.value) return;
    router.post(
        '/admin/backups/prepare-restore',
        {
            disk: restoreTarget.value.disk,
            path: restoreTarget.value.path,
            confirm: restoreConfirmText.value.trim(),
        },
        {
            preserveScroll: true,
            onFinish: cancelRestore,
        },
    );
}
</script>

<template>
    <Head title="Backups · Admin" />

    <PageHeader :title="t('admin.backups.title')" :description="t('admin.backups.subtitle')">
        <template #actions>
            <Button :label="t('admin.backups.run_now')" icon="pi pi-play" @click="runBackup" />
            <Button :label="t('admin.backups.clean')" icon="pi pi-trash" severity="secondary" @click="runClean" />
        </template>
    </PageHeader>

    <!-- Summary strip -->
    <div v-if="summaries.length" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div
            v-for="s in summaries"
            :key="s.disk"
            class="p-4 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800"
        >
            <div class="flex items-center justify-between mb-2">
                <Tag :value="s.disk" severity="info" />
                <span class="text-xs text-gray-500 dark:text-dark-400">{{ s.count }} {{ t('admin.backups.files') }}</span>
            </div>
            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ humanSize(s.used_bytes) }}</p>
            <p class="text-xs text-gray-500 dark:text-dark-400 mt-0.5">
                {{ s.newest_at ? t('admin.backups.newest_at', { when: formatDateTime(s.newest_at) }) : t('admin.backups.no_backups_yet') }}
            </p>
        </div>
    </div>

    <!-- Info / how it works -->
    <section class="mb-6 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 overflow-hidden">
        <button
            type="button"
            class="w-full flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-dark-800 cursor-pointer text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            :aria-expanded="infoOpen"
            aria-controls="backup-info-panel"
            @click="infoOpen = !infoOpen"
        >
            <div class="flex items-center gap-2">
                <i class="pi pi-info-circle text-gray-400"></i>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('admin.backups.how_it_works') }}</h2>
            </div>
            <i :class="['pi text-xs', infoOpen ? 'pi-chevron-up' : 'pi-chevron-down']"></i>
        </button>
        <div v-if="infoOpen" id="backup-info-panel" class="px-5 py-5 grid gap-6 md:grid-cols-2 text-sm">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-dark-400 mb-2">
                    {{ t('admin.backups.info_whats_included') }}
                </h3>
                <p class="text-gray-700 dark:text-gray-200 mb-3">
                    {{ t('admin.backups.info_whats_included_desc') }}
                </p>
                <dl class="space-y-2 text-xs">
                    <div v-if="config.databases.length" class="flex gap-2">
                        <dt class="w-24 shrink-0 text-gray-500 dark:text-dark-400">{{ t('admin.backups.databases') }}</dt>
                        <dd class="text-gray-800 dark:text-gray-100">
                            <code v-for="db in config.databases" :key="db" class="inline-block mr-1 px-1.5 py-0.5 bg-gray-100 dark:bg-dark-800 rounded">{{ db }}</code>
                        </dd>
                    </div>
                    <div v-if="config.includes.length" class="flex gap-2">
                        <dt class="w-24 shrink-0 text-gray-500 dark:text-dark-400">{{ t('admin.backups.includes') }}</dt>
                        <dd class="text-gray-800 dark:text-gray-100 break-all">
                            <code v-for="p in config.includes" :key="p" class="inline-block mr-1 px-1.5 py-0.5 bg-gray-100 dark:bg-dark-800 rounded">{{ p }}</code>
                        </dd>
                    </div>
                    <div v-if="config.excludes.length" class="flex gap-2">
                        <dt class="w-24 shrink-0 text-gray-500 dark:text-dark-400">{{ t('admin.backups.excludes') }}</dt>
                        <dd class="text-gray-800 dark:text-gray-100 break-all">
                            <code v-for="p in config.excludes" :key="p" class="inline-block mr-1 px-1.5 py-0.5 bg-gray-100 dark:bg-dark-800 rounded">{{ p }}</code>
                        </dd>
                    </div>
                </dl>
            </div>

            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-dark-400 mb-2">
                    {{ t('admin.backups.info_retention') }}
                </h3>
                <ul class="space-y-1.5 text-xs text-gray-700 dark:text-gray-200">
                    <li>{{ t('admin.backups.retention_daily', { n: config.retention_daily_keep }) }}</li>
                    <li>{{ t('admin.backups.retention_weekly', { n: config.retention_weekly_keep }) }}</li>
                    <li>{{ t('admin.backups.retention_monthly', { n: config.retention_monthly_keep }) }}</li>
                    <li>{{ t('admin.backups.retention_yearly', { n: config.retention_yearly_keep }) }}</li>
                    <li>{{ t('admin.backups.retention_max', { n: config.max_storage_mb }) }}</li>
                </ul>
                <p class="text-xs text-gray-500 dark:text-dark-400 mt-3">
                    {{ t('admin.backups.powered_by_spatie') }}
                    <a href="https://spatie.be/docs/laravel-backup" target="_blank" rel="noopener" class="text-indigo-600 dark:text-indigo-400 hover:underline">spatie/laravel-backup</a>.
                </p>
            </div>

            <div class="md:col-span-2 border-t border-gray-100 dark:border-dark-800 pt-5">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-dark-400 mb-2">
                    {{ t('admin.backups.info_how_to_restore') }}
                </h3>
                <ol class="space-y-2 text-sm text-gray-700 dark:text-gray-200 list-decimal list-inside">
                    <li>{{ t('admin.backups.restore_step_1') }}</li>
                    <li>{{ t('admin.backups.restore_step_2') }}</li>
                    <li>
                        {{ t('admin.backups.restore_step_3') }}
                        <code class="block mt-1 p-2 text-xs bg-gray-100 dark:bg-dark-800 rounded">mysql -u root -p {{ config.databases[0] ?? 'your_db' }} &lt; storage/app/backup-restores/&lt;timestamp&gt;/db-dumps/&lt;file&gt;.sql</code>
                    </li>
                    <li>{{ t('admin.backups.restore_step_4') }}</li>
                </ol>
                <p class="text-xs text-amber-600 dark:text-amber-400 mt-3 flex items-start gap-1.5">
                    <i class="pi pi-exclamation-triangle text-xs mt-0.5"></i>
                    {{ t('admin.backups.restore_warning') }}
                </p>
            </div>
        </div>
    </section>

    <!-- Backups table -->
    <DataTableShell hide-search :count="backups.length" :count-label="t('admin.backups.files')">
        <DataTable :value="backups" stripedRows removableSort scrollable class="border-0">
            <Column field="date" :header="t('admin.backups.date')" style="width: 14rem" sortable>
                <template #body="{ data }">{{ formatDateTime(data.date) }}</template>
            </Column>
            <Column field="disk" :header="t('admin.backups.disk')" style="width: 8rem" sortable>
                <template #body="{ data }"><Tag :value="data.disk" severity="info" /></template>
            </Column>
            <Column field="path" :header="t('admin.backups.file')" sortable>
                <template #body="{ data }">
                    <code class="text-xs text-gray-600 dark:text-dark-300 break-all">{{ data.path }}</code>
                </template>
            </Column>
            <Column field="size" :header="t('admin.backups.size')" style="width: 8rem" sortable>
                <template #body="{ data }">{{ humanSize(data.size) }}</template>
            </Column>
            <Column :header="t('common.actions')" style="width: 14rem">
                <template #body="{ data }">
                    <Button
                        as="a"
                        :href="downloadUrl(data)"
                        icon="pi pi-download"
                        size="small"
                        severity="secondary"
                        class="mr-2"
                        :title="t('admin.backups.download')"
                    />
                    <Button
                        icon="pi pi-replay"
                        size="small"
                        severity="warn"
                        :title="t('admin.backups.prepare_restore')"
                        @click="openRestore(data)"
                    />
                </template>
            </Column>
            <template #empty>
                <div class="px-6 py-10 text-center text-sm text-gray-500 dark:text-dark-400">
                    <i class="pi pi-cloud-upload text-2xl mb-2 block text-gray-300 dark:text-dark-600"></i>
                    {{ t('admin.backups.empty') }}
                </div>
            </template>
        </DataTable>
    </DataTableShell>

    <Dialog
        :visible="!!restoreTarget"
        modal
        :header="t('admin.backups.restore_dialog_title')"
        :style="{ width: '32rem' }"
        @update:visible="(v) => { if (!v) cancelRestore() }"
    >
        <p class="text-sm text-gray-700 dark:text-gray-200 mb-3">
            {{ t('admin.backups.restore_dialog_body') }}
        </p>
        <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-xs text-amber-800 dark:text-amber-200 mb-4">
            <p class="font-medium mb-1">{{ t('admin.backups.restore_dialog_warning_title') }}</p>
            <p>{{ t('admin.backups.restore_dialog_warning_body') }}</p>
        </div>
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1.5">
            {{ t('admin.backups.restore_dialog_confirm_label', { name: restoreFilename }) }}
        </label>
        <InputText v-model="restoreConfirmText" class="w-full" autocomplete="off" />
        <template #footer>
            <Button :label="t('common.cancel')" severity="secondary" @click="cancelRestore" />
            <Button
                :label="t('admin.backups.prepare_restore')"
                icon="pi pi-replay"
                severity="warn"
                :disabled="!restoreValid"
                @click="submitRestore"
            />
        </template>
    </Dialog>
</template>
