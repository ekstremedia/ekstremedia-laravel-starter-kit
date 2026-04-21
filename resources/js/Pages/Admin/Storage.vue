<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface TopUser {
    user_id: number;
    name: string;
    email: string;
    bytes: number;
}

interface CustomerUsage {
    tenant_id: number;
    name: string;
    slug: string;
    bytes: number;
    file_count: number;
}

interface GrowthPoint {
    date: string;
    bytes: number;
}

interface PageData {
    totals: {
        bytes: number;
        disk_total: number;
        disk_free: number;
        file_count: number;
        user_count: number;
        customer_count: number;
    };
    by_type: Record<string, number>;
    by_collection: Record<string, number>;
    by_customer: CustomerUsage[];
    top_users: TopUser[];
    growth: GrowthPoint[];
    filters: {
        user_search: string;
        customer_search: string;
    };
}

const props = defineProps<PageData>();
const { t } = useI18n();

const userSearch = ref(props.filters.user_search ?? '');
const customerSearch = ref(props.filters.customer_search ?? '');

// Reloads only the parts of the page that changed — uses Inertia's partial
// reloads so filter inputs don't cause a full-page flash.
function applyFilters() {
    router.get(
        '/admin/storage',
        {
            user_search: userSearch.value || undefined,
            customer_search: customerSearch.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function formatBytes(n: number): string {
    if (!n) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let v = n;
    while (v >= 1024 && i < units.length - 1) {
        v /= 1024;
        i++;
    }
    return `${v.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

const diskPercent = computed(() => {
    if (props.totals.disk_total === 0) return 0;
    return ((props.totals.disk_total - props.totals.disk_free) / props.totals.disk_total) * 100;
});

// Detect dark mode once — re-detect if user toggles theme via a MutationObserver.
const isDark = ref(document.documentElement.classList.contains('dark'));
let themeObserver: MutationObserver | null = null;
onMounted(() => {
    themeObserver = new MutationObserver(() => {
        isDark.value = document.documentElement.classList.contains('dark');
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});
onUnmounted(() => {
    themeObserver?.disconnect();
    themeObserver = null;
});

const chartTheme = computed(() => (isDark.value ? 'dark' : 'light'));
const fgColor = computed(() => (isDark.value ? '#cbd5e1' : '#475569'));

// Type-breakdown donut
const typeChartOptions = computed(() => ({
    chart: { type: 'donut', background: 'transparent' },
    theme: { mode: chartTheme.value },
    labels: Object.keys(props.by_type).map((k) => t(`admin.storage.type_${k}`, k.charAt(0).toUpperCase() + k.slice(1))),
    colors: ['#6366f1', '#f59e0b', '#10b981', '#0ea5e9', '#64748b'],
    legend: { position: 'bottom', labels: { colors: fgColor.value } },
    dataLabels: { enabled: true, formatter: (v: number) => `${v.toFixed(1)}%` },
    tooltip: { y: { formatter: (v: number) => formatBytes(v) } },
    stroke: { width: 0 },
}));
const typeChartSeries = computed(() => Object.values(props.by_type));

// Collection-breakdown donut — shows how much of the system total is
// user-billable storage vs the "on us" overhead (previews, chat, avatars).
const collectionLabels: Record<string, string> = {};
const collectionChartOptions = computed(() => ({
    chart: { type: 'donut', background: 'transparent' },
    theme: { mode: chartTheme.value },
    labels: Object.keys(props.by_collection).map((k) =>
        t(`admin.storage.collection_${k}`, k.replaceAll('_', ' '))
    ),
    colors: ['#6366f1', '#f59e0b', '#ec4899', '#10b981', '#0ea5e9', '#a855f7', '#64748b'],
    legend: { position: 'bottom', labels: { colors: fgColor.value } },
    dataLabels: { enabled: true, formatter: (v: number) => `${v.toFixed(1)}%` },
    tooltip: { y: { formatter: (v: number) => formatBytes(v) } },
    stroke: { width: 0 },
}));
const collectionChartSeries = computed(() => Object.values(props.by_collection));
// Touch the labels map to avoid lint warning about unused variable:
void collectionLabels;

// Top-users bar
const topChartOptions = computed(() => ({
    chart: { type: 'bar', background: 'transparent', toolbar: { show: false } },
    theme: { mode: chartTheme.value },
    plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
    colors: ['#6366f1'],
    xaxis: {
        categories: props.top_users.map((u) => u.name || u.email),
        labels: { style: { colors: fgColor.value }, formatter: (v: string) => formatBytes(Number(v)) },
    },
    yaxis: { labels: { style: { colors: fgColor.value } } },
    grid: { borderColor: isDark.value ? '#1e293b' : '#e2e8f0' },
    tooltip: { y: { formatter: (v: number) => formatBytes(v) } },
    dataLabels: { enabled: false },
}));
const topChartSeries = computed(() => [{
    name: t('admin.storage.bytes'),
    data: props.top_users.map((u) => u.bytes),
}]);

// Growth line
const growthChartOptions = computed(() => ({
    chart: { type: 'area', background: 'transparent', toolbar: { show: false } },
    theme: { mode: chartTheme.value },
    colors: ['#10b981'],
    xaxis: {
        type: 'datetime',
        categories: props.growth.map((p) => p.date),
        labels: { style: { colors: fgColor.value } },
    },
    yaxis: { labels: { style: { colors: fgColor.value }, formatter: (v: number) => formatBytes(v) } },
    stroke: { curve: 'smooth', width: 2 },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (v: number) => formatBytes(v) } },
    grid: { borderColor: isDark.value ? '#1e293b' : '#e2e8f0' },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0 } },
}));
const growthChartSeries = computed(() => [{
    name: t('admin.storage.bytes'),
    data: props.growth.map((p) => p.bytes),
}]);
</script>

<template>
    <AdminLayout>
        <Head :title="t('admin.storage.title')" />

        <div class="mx-auto max-w-7xl p-4 sm:p-6">
            <h1 class="mb-6 text-2xl font-semibold text-slate-800 dark:text-slate-100">
                {{ t('admin.storage.title') }}
            </h1>

            <!-- KPI cards -->
            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-dark-700 dark:bg-dark-900">
                    <div class="text-xs uppercase text-slate-500 dark:text-slate-400">{{ t('admin.storage.total_used') }}</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ formatBytes(props.totals.bytes) }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-dark-700 dark:bg-dark-900">
                    <div class="text-xs uppercase text-slate-500 dark:text-slate-400">{{ t('admin.storage.file_count') }}</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ props.totals.file_count.toLocaleString() }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-dark-700 dark:bg-dark-900">
                    <div class="text-xs uppercase text-slate-500 dark:text-slate-400">{{ t('admin.storage.users') }}</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ props.totals.user_count.toLocaleString() }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-dark-700 dark:bg-dark-900">
                    <div class="text-xs uppercase text-slate-500 dark:text-slate-400">{{ t('admin.storage.customers') }}</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ props.totals.customer_count.toLocaleString() }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-dark-700 dark:bg-dark-900">
                    <div class="text-xs uppercase text-slate-500 dark:text-slate-400">{{ t('admin.storage.disk_usage') }}</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ diskPercent.toFixed(0) }}%</div>
                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ formatBytes(props.totals.disk_total - props.totals.disk_free) }} / {{ formatBytes(props.totals.disk_total) }}
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-dark-700 dark:bg-dark-900">
                    <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ t('admin.storage.by_type') }}</h2>
                    <apexchart
                        v-if="typeChartSeries.some((v) => v > 0)"
                        type="donut"
                        height="280"
                        :options="typeChartOptions"
                        :series="typeChartSeries"
                    />
                    <p v-else class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        {{ t('admin.storage.no_data') }}
                    </p>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-dark-700 dark:bg-dark-900">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ t('admin.storage.top_users') }}</h2>
                        <input
                            v-model="userSearch"
                            type="search"
                            :placeholder="t('admin.storage.search_users')"
                            class="w-48 rounded-md border border-slate-300 bg-white px-2 py-1 text-xs dark:border-dark-700 dark:bg-dark-800 dark:text-slate-100"
                            @keyup.enter="applyFilters"
                            @search="applyFilters"
                        />
                    </div>
                    <apexchart
                        v-if="props.top_users.length"
                        type="bar"
                        height="280"
                        :options="topChartOptions"
                        :series="topChartSeries"
                    />
                    <p v-else class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        {{ t('admin.storage.no_data') }}
                    </p>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-dark-700 dark:bg-dark-900 lg:col-span-2">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ t('admin.storage.by_customer') }}</h2>
                        <input
                            v-model="customerSearch"
                            type="search"
                            :placeholder="t('admin.storage.search_customers')"
                            class="w-64 rounded-md border border-slate-300 bg-white px-2 py-1 text-xs dark:border-dark-700 dark:bg-dark-800 dark:text-slate-100"
                            @keyup.enter="applyFilters"
                            @search="applyFilters"
                        />
                    </div>
                    <div v-if="props.by_customer.length" class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase text-slate-500 dark:text-slate-400">
                                <tr>
                                    <th class="px-2 py-1 font-medium">{{ t('admin.storage.customer') }}</th>
                                    <th class="px-2 py-1 font-medium">{{ t('admin.storage.slug') }}</th>
                                    <th class="px-2 py-1 text-right font-medium">{{ t('admin.storage.file_count') }}</th>
                                    <th class="px-2 py-1 text-right font-medium">{{ t('admin.storage.bytes') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-dark-800">
                                <tr v-for="c in props.by_customer" :key="c.tenant_id">
                                    <td class="px-2 py-1.5 text-slate-700 dark:text-slate-200">{{ c.name }}</td>
                                    <td class="px-2 py-1.5 text-slate-500 dark:text-slate-400">{{ c.slug }}</td>
                                    <td class="px-2 py-1.5 text-right text-slate-500 dark:text-slate-400">{{ c.file_count.toLocaleString() }}</td>
                                    <td class="px-2 py-1.5 text-right font-medium text-slate-700 dark:text-slate-200">{{ formatBytes(c.bytes) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        {{ t('admin.storage.no_data') }}
                    </p>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-dark-700 dark:bg-dark-900 lg:col-span-2">
                    <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ t('admin.storage.by_collection') }}</h2>
                    <apexchart
                        v-if="collectionChartSeries.some((v) => v > 0)"
                        type="donut"
                        height="260"
                        :options="collectionChartOptions"
                        :series="collectionChartSeries"
                    />
                    <p v-else class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        {{ t('admin.storage.no_data') }}
                    </p>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-dark-700 dark:bg-dark-900 lg:col-span-2">
                    <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ t('admin.storage.growth_30d') }}</h2>
                    <apexchart
                        v-if="props.growth.length"
                        type="area"
                        height="280"
                        :options="growthChartOptions"
                        :series="growthChartSeries"
                    />
                    <p v-else class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        {{ t('admin.storage.no_snapshots') }}
                    </p>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
