<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
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
interface Activity { id: number; log_name: string | null; description: string; event: string | null; subject_type: string | null; subject_id: number | null; causer: Causer | null; created_at: string; properties: any }
interface Props {
    activities: { data: Activity[]; links: any };
    filters: { user_id?: number | null; log_name?: string | null; event?: string | null; date_from?: string | null; date_to?: string | null };
    users: Causer[];
    logNames: string[];
    events: string[];
}
const props = defineProps<Props>();

const f = ref({
    user_id: props.filters.user_id ?? null,
    log_name: props.filters.log_name ?? null,
    event: props.filters.event ?? null,
    date_from: props.filters.date_from ? new Date(props.filters.date_from) : null as Date | null,
    date_to: props.filters.date_to ? new Date(props.filters.date_to) : null as Date | null,
});

function apply() {
    router.get('/admin/activity', {
        user_id: f.value.user_id || undefined,
        log_name: f.value.log_name || undefined,
        event: f.value.event || undefined,
        date_from: f.value.date_from ? f.value.date_from.toISOString().slice(0, 10) : undefined,
        date_to: f.value.date_to ? f.value.date_to.toISOString().slice(0, 10) : undefined,
    }, { preserveState: true, replace: true });
}

function reset() {
    f.value = { user_id: null, log_name: null, event: null, date_from: null, date_to: null };
    apply();
}
</script>

<template>
    <Head title="Activity Log · Admin" />
    <h1 class="text-2xl font-semibold mb-6">{{ t('admin.activity.title') }}</h1>

    <div class="mb-6 p-4 bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl grid grid-cols-2 md:grid-cols-5 gap-3">
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

    <DataTable :value="activities.data" stripedRows removableSort scrollable
               class="bg-white dark:bg-dark-900 rounded-xl overflow-hidden">
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

    <div class="mt-4 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <template v-for="link in activities.links" :key="link.label">
            <Link v-if="link.url" :href="link.url"
                  class="px-3 py-1 rounded border border-gray-200 dark:border-dark-700 hover:bg-gray-100 dark:hover:bg-dark-800"
                  :class="{ 'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700': link.active }"
                  v-html="link.label" />
            <span v-else class="px-3 py-1 text-gray-400" v-html="link.label"></span>
        </template>
    </div>
</template>
