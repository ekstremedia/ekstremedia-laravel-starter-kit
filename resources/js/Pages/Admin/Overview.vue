<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import VueApexCharts from 'vue3-apexcharts';
import type { ApexOptions } from 'apexcharts';
import Tag from 'primevue/tag';
import { formatDateTime } from '@/composables/useDateTime';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface TrendPoint { date: string; count: number }
interface RecentActivity {
    id: number;
    description: string;
    log_name: string | null;
    event: string | null;
    created_at: string | null;
    causer: { id: number; email: string | null; first_name: string | null; last_name: string | null } | null;
}
interface Metrics {
    generated_at: string;
    users: { total: number; unverified: number; banned: number; new_last_7d: number; trend_30d: TrendPoint[] };
    customers: { total: number; active: number; suspended: number } | null;
    storage: { used_bytes: number; quota_bytes: number };
    queue: { pending: number; failed: number };
    backups: { disk?: string; count: number; last_at: string | null; last_size_bytes: number | null };
    activity: { total: number; trend_30d: TrendPoint[] };
    recent_activity: RecentActivity[];
}

const props = defineProps<{ metrics: Metrics }>();

const metrics = ref<Metrics>(props.metrics);
let pollHandle: ReturnType<typeof setInterval> | null = null;

async function refresh() {
    try {
        const res = await fetch('/admin/overview/metrics', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) return;
        metrics.value = (await res.json()) as Metrics;
    } catch {
        // swallow — a missed poll tick is fine; next tick will try again.
    }
}

onMounted(() => {
    // Live refresh every 30s. Keep the cadence low to avoid load on local dev setups.
    pollHandle = setInterval(refresh, 30_000);
});
onBeforeUnmount(() => {
    if (pollHandle) clearInterval(pollHandle);
});

function formatBytes(n: number | null | undefined): string {
    if (!n || n <= 0) return '—';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let v = n;
    while (v >= 1024 && i < units.length - 1) {
        v /= 1024;
        i++;
    }
    return `${v.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function relativeTime(iso: string | null | undefined): string {
    if (!iso) return '—';
    const diffMs = Date.now() - new Date(iso).getTime();
    if (diffMs < 0) return t('admin.dashboard.just_now');
    const sec = Math.floor(diffMs / 1000);
    if (sec < 60) return t('admin.dashboard.seconds_ago', { n: sec });
    const min = Math.floor(sec / 60);
    if (min < 60) return t('admin.dashboard.minutes_ago', { n: min });
    const hr = Math.floor(min / 60);
    if (hr < 24) return t('admin.dashboard.hours_ago', { n: hr });
    const day = Math.floor(hr / 24);
    if (day < 30) return t('admin.dashboard.days_ago', { n: day });
    return formatDateTime(iso);
}

function causerLabel(a: RecentActivity): string {
    if (!a.causer) return t('admin.activity.system');
    const name = [a.causer.first_name, a.causer.last_name].filter(Boolean).join(' ');
    return name || a.causer.email || `#${a.causer.id}`;
}

const isDark = computed(() => {
    if (typeof document === 'undefined') return false;
    return document.documentElement.classList.contains('dark');
});

const usersChart = computed(() => ({
    options: {
        chart: { type: 'area', toolbar: { show: false }, sparkline: { enabled: false }, animations: { enabled: false }, fontFamily: 'inherit' },
        stroke: { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
        colors: ['#6366f1'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 0.3, opacityFrom: 0.35, opacityTo: 0 } },
        grid: { strokeDashArray: 4, borderColor: isDark.value ? '#1f2937' : '#e5e7eb' },
        xaxis: {
            type: 'datetime',
            categories: metrics.value.users.trend_30d.map((p) => p.date),
            labels: { style: { colors: isDark.value ? '#9ca3af' : '#6b7280', fontSize: '11px' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { labels: { style: { colors: isDark.value ? '#9ca3af' : '#6b7280', fontSize: '11px' } } },
        tooltip: { theme: isDark.value ? 'dark' : 'light', x: { format: 'MMM d' } },
    } as ApexOptions,
    series: [{ name: t('admin.dashboard.signups'), data: metrics.value.users.trend_30d.map((p) => p.count) }],
}));

const activityChart = computed(() => ({
    options: {
        chart: { type: 'bar', toolbar: { show: false }, animations: { enabled: false }, fontFamily: 'inherit' },
        plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
        dataLabels: { enabled: false },
        colors: ['#22c55e'],
        grid: { strokeDashArray: 4, borderColor: isDark.value ? '#1f2937' : '#e5e7eb' },
        xaxis: {
            type: 'datetime',
            categories: metrics.value.activity.trend_30d.map((p) => p.date),
            labels: { style: { colors: isDark.value ? '#9ca3af' : '#6b7280', fontSize: '11px' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { labels: { style: { colors: isDark.value ? '#9ca3af' : '#6b7280', fontSize: '11px' } } },
        tooltip: { theme: isDark.value ? 'dark' : 'light', x: { format: 'MMM d' } },
    } as ApexOptions,
    series: [{ name: t('admin.dashboard.events'), data: metrics.value.activity.trend_30d.map((p) => p.count) }],
}));

interface StatCard {
    key: string;
    label: string;
    value: string;
    sub?: string;
    icon: string;
    href?: string;
    tone?: 'ok' | 'warn' | 'danger' | 'neutral';
}

const stats = computed<StatCard[]>(() => {
    const m = metrics.value;
    const cards: StatCard[] = [
        {
            key: 'users',
            label: t('admin.dashboard.users'),
            value: m.users.total.toLocaleString(),
            sub: t('admin.dashboard.new_this_week', { n: m.users.new_last_7d }),
            icon: 'pi-users',
            href: '/admin/users',
            tone: 'neutral',
        },
    ];

    if (m.customers) {
        cards.push({
            key: 'customers',
            label: t('admin.dashboard.customers'),
            value: m.customers.total.toLocaleString(),
            sub: t('admin.dashboard.active_n', { n: m.customers.active }),
            icon: 'pi-building',
            href: '/admin/customers',
            tone: m.customers.suspended > 0 ? 'warn' : 'ok',
        });
    }

    cards.push({
        key: 'storage',
        label: t('admin.dashboard.storage'),
        value: formatBytes(m.storage.used_bytes),
        sub: m.storage.quota_bytes > 0 ? t('admin.dashboard.of_quota', { q: formatBytes(m.storage.quota_bytes) }) : t('admin.dashboard.no_quota'),
        icon: 'pi-database',
        href: '/admin/storage',
        tone: 'neutral',
    });

    cards.push({
        key: 'queue',
        label: t('admin.dashboard.queue'),
        value: m.queue.pending.toLocaleString(),
        sub: m.queue.failed > 0 ? t('admin.dashboard.failed_n', { n: m.queue.failed }) : t('admin.dashboard.no_failed'),
        icon: 'pi-bolt',
        href: '/horizon',
        tone: m.queue.failed > 0 ? 'danger' : 'ok',
    });

    cards.push({
        key: 'backups',
        label: t('admin.dashboard.last_backup'),
        value: m.backups.last_at ? relativeTime(m.backups.last_at) : t('admin.dashboard.never'),
        sub: m.backups.last_size_bytes ? formatBytes(m.backups.last_size_bytes) : t('admin.dashboard.no_backups'),
        icon: 'pi-cloud-upload',
        href: '/admin/backups',
        tone: m.backups.last_at ? 'ok' : 'warn',
    });

    cards.push({
        key: 'unverified',
        label: t('admin.dashboard.unverified'),
        value: m.users.unverified.toLocaleString(),
        sub: m.users.banned > 0 ? t('admin.dashboard.banned_n', { n: m.users.banned }) : t('admin.dashboard.all_good'),
        icon: 'pi-user-minus',
        href: '/admin/users',
        tone: m.users.unverified > 0 ? 'warn' : 'ok',
    });

    return cards;
});

const toneStyles: Record<string, string> = {
    ok: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    warn: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    danger: 'bg-red-500/10 text-red-600 dark:text-red-400',
    neutral: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
};

interface QuickLink { label: string; icon: string; href: string; external?: boolean }
const quickLinks = computed<QuickLink[]>(() => [
    { label: t('admin.nav.roles'), icon: 'pi-shield', href: '/admin/roles' },
    { label: t('admin.nav.permissions'), icon: 'pi-key', href: '/admin/permissions' },
    { label: t('admin.nav.mail_settings'), icon: 'pi-envelope', href: '/admin/mail' },
    { label: t('admin.nav.app_settings'), icon: 'pi-sliders-h', href: '/admin/settings' },
    { label: t('admin.nav.system'), icon: 'pi-server', href: '/admin/system' },
    { label: t('admin.nav.monitoring'), icon: 'pi-chart-bar', href: '/admin/monitoring' },
]);

const generatedAt = computed(() => relativeTime(metrics.value.generated_at));
</script>

<template>
    <Head title="Admin · Dashboard" />

    <PageHeader :title="t('admin.dashboard.title')">
        <template #actions>
            <span class="text-xs text-gray-500 dark:text-dark-400 inline-flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                {{ t('admin.dashboard.updated', { when: generatedAt }) }}
            </span>
        </template>
    </PageHeader>

    <!-- Stat grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6 gap-4 mb-6">
        <template v-for="stat in stats" :key="stat.key">
            <a
                v-if="stat.href && (stat.href.startsWith('/horizon') || stat.href.startsWith('/pulse') || stat.href.startsWith('/log-viewer'))"
                :href="stat.href"
                target="_blank"
                rel="noopener"
                class="group block p-4 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 hover:border-gray-300 dark:hover:border-dark-700 transition-colors"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-dark-400 mb-1.5">
                            {{ stat.label }}
                        </p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white truncate">{{ stat.value }}</p>
                        <p v-if="stat.sub" class="text-xs text-gray-500 dark:text-dark-400 mt-1 truncate">{{ stat.sub }}</p>
                    </div>
                    <span :class="['w-9 h-9 rounded-lg inline-flex items-center justify-center shrink-0', toneStyles[stat.tone ?? 'neutral']]">
                        <i :class="['pi', stat.icon, 'text-sm']"></i>
                    </span>
                </div>
            </a>
            <Link
                v-else-if="stat.href"
                :href="stat.href"
                class="group block p-4 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 hover:border-gray-300 dark:hover:border-dark-700 transition-colors"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-dark-400 mb-1.5">
                            {{ stat.label }}
                        </p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white truncate">{{ stat.value }}</p>
                        <p v-if="stat.sub" class="text-xs text-gray-500 dark:text-dark-400 mt-1 truncate">{{ stat.sub }}</p>
                    </div>
                    <span :class="['w-9 h-9 rounded-lg inline-flex items-center justify-center shrink-0', toneStyles[stat.tone ?? 'neutral']]">
                        <i :class="['pi', stat.icon, 'text-sm']"></i>
                    </span>
                </div>
            </Link>
            <div
                v-else
                class="p-4 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-dark-400 mb-1.5">
                            {{ stat.label }}
                        </p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white truncate">{{ stat.value }}</p>
                        <p v-if="stat.sub" class="text-xs text-gray-500 dark:text-dark-400 mt-1 truncate">{{ stat.sub }}</p>
                    </div>
                    <span :class="['w-9 h-9 rounded-lg inline-flex items-center justify-center shrink-0', toneStyles[stat.tone ?? 'neutral']]">
                        <i :class="['pi', stat.icon, 'text-sm']"></i>
                    </span>
                </div>
            </div>
        </template>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <section class="p-5 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800">
            <header class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('admin.dashboard.signups_title') }}</h2>
                    <p class="text-xs text-gray-500 dark:text-dark-400">{{ t('admin.dashboard.last_30_days') }}</p>
                </div>
                <Link href="/admin/users" class="text-xs text-gray-500 dark:text-dark-400 hover:text-gray-900 dark:hover:text-white">
                    {{ t('common.view') }} →
                </Link>
            </header>
            <VueApexCharts type="area" height="220" :options="usersChart.options" :series="usersChart.series" />
        </section>

        <section class="p-5 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800">
            <header class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('admin.dashboard.activity_title') }}</h2>
                    <p class="text-xs text-gray-500 dark:text-dark-400">{{ t('admin.dashboard.last_30_days') }}</p>
                </div>
                <Link href="/admin/monitoring" class="text-xs text-gray-500 dark:text-dark-400 hover:text-gray-900 dark:hover:text-white">
                    {{ t('common.view') }} →
                </Link>
            </header>
            <VueApexCharts type="bar" height="220" :options="activityChart.options" :series="activityChart.series" />
        </section>
    </div>

    <!-- Recent activity + quick links -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <section class="lg:col-span-2 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 overflow-hidden">
            <header class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-dark-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('admin.dashboard.recent_activity') }}</h2>
                <Link href="/admin/monitoring?tab=activity" class="text-xs text-gray-500 dark:text-dark-400 hover:text-gray-900 dark:hover:text-white">
                    {{ t('common.view_all') }} →
                </Link>
            </header>
            <ul v-if="metrics.recent_activity.length" class="divide-y divide-gray-100 dark:divide-dark-800">
                <li v-for="a in metrics.recent_activity" :key="a.id" class="px-5 py-3 flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-dark-800 flex items-center justify-center text-gray-500 dark:text-dark-400 shrink-0">
                        <i class="pi pi-user text-xs"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-900 dark:text-white">
                            <span class="font-medium">{{ causerLabel(a) }}</span>
                            <span class="text-gray-500 dark:text-dark-400"> · </span>
                            <span class="text-gray-700 dark:text-gray-200">{{ a.description }}</span>
                        </p>
                        <div class="flex items-center gap-2 mt-0.5 text-xs text-gray-500 dark:text-dark-400">
                            <Tag v-if="a.log_name" :value="a.log_name" severity="secondary" class="text-[10px]" />
                            <Tag v-if="a.event" :value="a.event" severity="info" class="text-[10px]" />
                            <span>{{ relativeTime(a.created_at) }}</span>
                        </div>
                    </div>
                </li>
            </ul>
            <div v-else class="px-5 py-12 text-center text-sm text-gray-500 dark:text-dark-400">
                <i class="pi pi-inbox text-2xl mb-2 block text-gray-300 dark:text-dark-600"></i>
                {{ t('admin.dashboard.no_activity') }}
            </div>
        </section>

        <section class="rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 overflow-hidden">
            <header class="px-5 py-3 border-b border-gray-200 dark:border-dark-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('admin.dashboard.quick_links') }}</h2>
            </header>
            <div class="grid grid-cols-2 gap-px bg-gray-200 dark:bg-dark-800">
                <Link
                    v-for="link in quickLinks"
                    :key="link.href"
                    :href="link.href"
                    class="flex flex-col items-center justify-center gap-2 py-5 bg-white dark:bg-dark-900 hover:bg-gray-50 dark:hover:bg-dark-800 transition-colors text-center"
                >
                    <i :class="['pi', link.icon, 'text-lg text-gray-500 dark:text-dark-400']"></i>
                    <span class="text-xs font-medium text-gray-700 dark:text-gray-200">{{ link.label }}</span>
                </Link>
            </div>
        </section>
    </div>
</template>
