<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTableShell from '@/Components/Admin/DataTableShell.vue';
import PaginationLinks from '@/Components/Admin/PaginationLinks.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useI18n } from 'vue-i18n';
import { formatDateTime } from '@/composables/useDateTime';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface Causer { id: number; email: string; first_name: string; last_name: string }
interface Activity {
    id: number;
    log_name: string | null;
    description: string;
    event: string | null;
    subject_type: string | null;
    subject_id: number | null;
    causer: Causer | null;
    created_at: string;
    properties: any;
}
interface PaginatedActivities {
    data: Activity[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total?: number;
}

interface Props {
    tab: 'activity' | 'logs' | 'pulse' | 'horizon';
    // Activity-only payload. Nullable / empty on iframe tabs where the server
    // skips the DB queries to save a round-trip.
    activities?: PaginatedActivities | null;
    filters: {
        user_id?: number | null;
        log_name?: string | null;
        event?: string | null;
        date_from?: string | null;
        date_to?: string | null;
    };
    users?: Causer[];
    logNames?: string[];
    events?: string[];
    endpoints: { logs: string; pulse: string; horizon: string };
}
const props = withDefaults(defineProps<Props>(), {
    activities: null,
    users: () => [],
    logNames: () => [],
    events: () => [],
});

function parseLocalDate(value?: string | null): Date | null {
    if (!value) return null;
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (!match) return null;
    return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
}

function formatLocalDate(value: Date | null): string | undefined {
    if (!value) return undefined;
    const y = value.getFullYear();
    const m = String(value.getMonth() + 1).padStart(2, '0');
    const d = String(value.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

const activeTab = ref<Props['tab']>(props.tab);

watch(activeTab, (next) => {
    if (next === props.tab) return;
    router.get(
        '/admin/monitoring',
        { tab: next },
        { preserveState: true, preserveScroll: true, replace: true },
    );
});

const f = ref({
    user_id: props.filters.user_id ?? null,
    log_name: props.filters.log_name ?? null,
    event: props.filters.event ?? null,
    date_from: parseLocalDate(props.filters.date_from),
    date_to: parseLocalDate(props.filters.date_to),
});

function apply() {
    router.get(
        '/admin/monitoring',
        {
            tab: 'activity',
            user_id: f.value.user_id || undefined,
            log_name: f.value.log_name || undefined,
            event: f.value.event || undefined,
            date_from: formatLocalDate(f.value.date_from),
            date_to: formatLocalDate(f.value.date_to),
        },
        { preserveState: true, replace: true },
    );
}

function reset() {
    f.value = { user_id: null, log_name: null, event: null, date_from: null, date_to: null };
    apply();
}

interface TabItem { key: Props['tab']; label: string; icon: string; external?: string }

const tabs = computed<TabItem[]>(() => [
    { key: 'activity', label: t('admin.monitoring.tab_activity'), icon: 'pi-list' },
    { key: 'logs', label: t('admin.monitoring.tab_logs'), icon: 'pi-file', external: props.endpoints.logs },
    { key: 'pulse', label: t('admin.monitoring.tab_pulse'), icon: 'pi-chart-line', external: props.endpoints.pulse },
    { key: 'horizon', label: t('admin.monitoring.tab_horizon'), icon: 'pi-compass', external: props.endpoints.horizon },
]);

const iframeUrl = computed(() => {
    if (activeTab.value === 'logs') return props.endpoints.logs;
    if (activeTab.value === 'pulse') return props.endpoints.pulse;
    if (activeTab.value === 'horizon') return props.endpoints.horizon;
    return null;
});
</script>

<template>
    <div>
        <Head :title="t('admin.monitoring.head_title')" />

        <PageHeader :title="t('admin.monitoring.title')" :description="t('admin.monitoring.description')" />

    <!-- Tab rail -->
    <div class="mb-4 border-b border-gray-200 dark:border-dark-800">
        <nav class="flex flex-wrap gap-1">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                @click="activeTab = tab.key"
                class="relative inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium transition-colors cursor-pointer border-b-2 -mb-px"
                :class="
                    activeTab === tab.key
                        ? 'text-gray-900 dark:text-white border-gray-900 dark:border-white'
                        : 'text-gray-500 dark:text-dark-400 hover:text-gray-900 dark:hover:text-white border-transparent'
                "
            >
                <i :class="['pi', tab.icon, 'text-xs']"></i>
                <span>{{ tab.label }}</span>
            </button>
        </nav>
    </div>

    <!-- Activity tab -->
    <div v-if="activeTab === 'activity'">
        <div class="mb-4 p-4 bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl grid grid-cols-2 md:grid-cols-5 gap-3">
            <Select v-model="f.user_id" :options="users" optionLabel="email" optionValue="id" :placeholder="t('admin.activity.user')" showClear class="w-full" />
            <Select v-model="f.log_name" :options="logNames" :placeholder="t('admin.activity.log_name')" showClear class="w-full" />
            <Select v-model="f.event" :options="events" :placeholder="t('admin.activity.event')" showClear class="w-full" />
            <DatePicker v-model="f.date_from" :placeholder="t('admin.activity.from')" dateFormat="yy-mm-dd" class="w-full" />
            <DatePicker v-model="f.date_to" :placeholder="t('admin.activity.to')" dateFormat="yy-mm-dd" class="w-full" />
            <div class="col-span-2 md:col-span-5 flex gap-2">
                <Button :label="t('admin.activity.apply')" icon="pi pi-filter" @click="apply" />
                <Button :label="t('admin.activity.reset')" severity="secondary" @click="reset" />
            </div>
        </div>

        <DataTableShell hide-search :count="activities?.total ?? activities?.data?.length ?? 0" :count-label="t('admin.activity.title').toLowerCase()">
            <DataTable :value="activities?.data ?? []" stripedRows removableSort scrollable class="border-0">
                <Column field="created_at" :header="t('admin.activity.when')" style="width: 12rem" sortable>
                    <template #body="{ data }">{{ formatDateTime(data.created_at) }}</template>
                </Column>
                <Column :header="t('admin.activity.user')" sortable :sortField="(d: any) => d.causer?.email ?? ''">
                    <template #body="{ data }">
                        <span v-if="data.causer">{{ data.causer.email }}</span>
                        <span v-else class="text-gray-400">{{ t('admin.activity.system') }}</span>
                    </template>
                </Column>
                <Column field="log_name" :header="t('admin.activity.log_name')" sortable>
                    <template #body="{ data }"><Tag v-if="data.log_name" :value="data.log_name" severity="info" /></template>
                </Column>
                <Column field="event" :header="t('admin.activity.event')" sortable>
                    <template #body="{ data }"><Tag v-if="data.event" :value="data.event" /></template>
                </Column>
                <Column field="description" :header="t('admin.activity.description')" sortable />
                <Column :header="t('admin.activity.subject')">
                    <template #body="{ data }">
                        <span v-if="data.subject_type" class="text-xs text-gray-500">{{ data.subject_type.split('\\').pop() }}#{{ data.subject_id }}</span>
                    </template>
                </Column>
            </DataTable>
        </DataTableShell>

        <PaginationLinks :links="activities?.links ?? null" />
    </div>

    <!-- Embedded dashboards -->
    <div v-else class="bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 dark:border-dark-800 bg-gray-50 dark:bg-dark-950/40">
            <p class="text-xs text-gray-500 dark:text-dark-400">
                {{ t('admin.monitoring.iframe_hint') }}
            </p>
            <a
                v-if="iframeUrl"
                :href="iframeUrl"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-600 dark:text-dark-300 hover:text-gray-900 dark:hover:text-white"
            >
                {{ t('admin.monitoring.open_new_tab') }}
                <i class="pi pi-external-link text-[10px]"></i>
            </a>
        </div>
        <iframe
            v-if="iframeUrl"
            :key="iframeUrl"
            :src="iframeUrl"
            :title="activeTab"
            class="w-full bg-white"
            style="height: calc(100vh - 16rem); min-height: 520px;"
            loading="lazy"
        />
    </div>
    </div>
</template>
