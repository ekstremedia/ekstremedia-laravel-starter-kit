<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import { gsap } from 'gsap';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Tag from 'primevue/tag';
import Button from 'primevue/button';

defineOptions({ layout: AdminLayout });

interface Props {
    php: Record<string, string>;
    system: Record<string, string>;
    laravel: Record<string, string | boolean>;
    drivers: Record<string, string>;
    cache_status: Record<string, boolean>;
    extensions: string[];
    health: {
        queue: { last: { nonce: string; at: string } | null; driver: string };
        broadcast: { driver: string };
        redis: { ok: boolean; pong?: string; error?: string };
    };
}
const props = defineProps<Props>();

const queueLast = ref(props.health.queue.last);
const broadcastLast = ref<{ nonce: string; at: string } | null>(null);
const queueCard = ref<HTMLElement | null>(null);
const broadcastCard = ref<HTMLElement | null>(null);
const extensionsOpen = ref(false);

function pingQueue() {
    router.post('/admin/health/queue', {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => pollQueue(),
    });
}

function pingBroadcast() {
    router.post('/admin/health/broadcast', {}, { preserveScroll: true, preserveState: true });
}

async function pollQueue() {
    for (let i = 0; i < 10; i++) {
        await new Promise((r) => setTimeout(r, 400));
        const res = await fetch('/admin/health/queue-last', { headers: { Accept: 'application/json' } });
        if (!res.ok) break;
        const json = await res.json();
        if (json.last && json.last.nonce !== queueLast.value?.nonce) {
            queueLast.value = json.last;
            if (queueCard.value) gsap.fromTo(queueCard.value, { backgroundColor: 'rgba(34,197,94,0.25)' }, { backgroundColor: 'transparent', duration: 1.2 });
            break;
        }
    }
}

let echoChannel: any = null;
onMounted(() => {
    const echo = (window as any).Echo;
    if (echo) {
        echoChannel = echo.private('admin.health').listen('.ping', (e: { nonce: string; at: string }) => {
            broadcastLast.value = e;
            if (broadcastCard.value) gsap.fromTo(broadcastCard.value, { backgroundColor: 'rgba(59,130,246,0.25)' }, { backgroundColor: 'transparent', duration: 1.2 });
        });
    }
});
onUnmounted(() => {
    const echo = (window as any).Echo;
    if (echo && echoChannel) echo.leave('admin.health');
});

const driverIcons: Record<string, string> = {
    broadcast: 'pi-wifi',
    cache: 'pi-bolt',
    database: 'pi-database',
    logs: 'pi-file',
    mail: 'pi-envelope',
    queue: 'pi-inbox',
    session: 'pi-id-card',
    filesystem: 'pi-folder',
};

function formatTime(iso: string | undefined) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}
</script>

<template>
    <Head title="System · Admin" />

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">Server &amp; System</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Live health probes and runtime snapshot.</p>
        </div>
    </div>

    <!-- Health row -->
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
        <div ref="queueCard" class="p-6 rounded-2xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 transition-colors">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center"><i class="pi pi-inbox text-lg"></i></div>
                    <div>
                        <h2 class="font-semibold">Queue</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Redis · Horizon</p>
                    </div>
                </div>
                <Tag :value="health.queue.driver" severity="info" />
            </div>
            <p class="text-xs uppercase tracking-wide text-gray-400 mb-1">Last completed ping</p>
            <p v-if="queueLast" class="text-sm font-mono">{{ formatTime(queueLast.at) }}<br><span class="text-xs opacity-60">{{ queueLast.nonce }}</span></p>
            <p v-else class="text-sm text-gray-400 italic">No pings yet.</p>
            <Button label="Dispatch ping job" icon="pi pi-play" size="small" severity="secondary" class="mt-4 w-full" @click="pingQueue" />
        </div>

        <div ref="broadcastCard" class="p-6 rounded-2xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 transition-colors">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center"><i class="pi pi-wifi text-lg"></i></div>
                    <div>
                        <h2 class="font-semibold">Broadcast</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Reverb · WebSocket</p>
                    </div>
                </div>
                <Tag :value="health.broadcast.driver" severity="info" />
            </div>
            <p class="text-xs uppercase tracking-wide text-gray-400 mb-1">Last received event</p>
            <p v-if="broadcastLast" class="text-sm font-mono">{{ formatTime(broadcastLast.at) }}<br><span class="text-xs opacity-60">{{ broadcastLast.nonce }}</span></p>
            <p v-else class="text-sm text-gray-400 italic">Listening on <code>admin.health</code>…</p>
            <Button label="Broadcast ping event" icon="pi pi-send" size="small" severity="secondary" class="mt-4 w-full" @click="pingBroadcast" />
        </div>

        <div class="p-6 rounded-2xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-500 flex items-center justify-center"><i class="pi pi-database text-lg"></i></div>
                    <div>
                        <h2 class="font-semibold">Redis</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Cache · Queue · Session</p>
                    </div>
                </div>
                <Tag :value="health.redis.ok ? 'connected' : 'down'" :severity="health.redis.ok ? 'success' : 'danger'" />
            </div>
            <p class="text-xs uppercase tracking-wide text-gray-400 mb-1">PING</p>
            <p v-if="health.redis.ok" class="text-2xl font-mono text-emerald-500">→ {{ health.redis.pong }}</p>
            <p v-else class="text-sm text-red-500 font-mono">{{ health.redis.error }}</p>
        </div>
    </section>

    <!-- Key-stat strip -->
    <section class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="p-5 rounded-2xl bg-gradient-to-br from-indigo-500/10 to-indigo-500/5 border border-indigo-500/20">
            <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-indigo-400 mb-2"><i class="pi pi-code"></i>PHP</div>
            <p class="text-2xl font-semibold font-mono">{{ php.version }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ php.sapi }}</p>
        </div>
        <div class="p-5 rounded-2xl bg-gradient-to-br from-rose-500/10 to-rose-500/5 border border-rose-500/20">
            <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-rose-400 mb-2"><i class="pi pi-box"></i>Laravel</div>
            <p class="text-2xl font-semibold font-mono">{{ laravel.version }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ laravel.environment }}{{ laravel.debug ? ' · debug' : '' }}</p>
        </div>
        <div class="p-5 rounded-2xl bg-gradient-to-br from-emerald-500/10 to-emerald-500/5 border border-emerald-500/20">
            <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-emerald-400 mb-2"><i class="pi pi-server"></i>Host</div>
            <p class="text-xl font-semibold font-mono truncate">{{ system.hostname }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ system.os }}</p>
        </div>
        <div class="p-5 rounded-2xl bg-gradient-to-br from-amber-500/10 to-amber-500/5 border border-amber-500/20">
            <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-amber-400 mb-2"><i class="pi pi-th-large"></i>Extensions</div>
            <p class="text-2xl font-semibold">{{ extensions.length }}</p>
            <p class="text-xs text-gray-500 mt-1">loaded</p>
        </div>
    </section>

    <!-- Drivers grid -->
    <section class="mb-8">
        <h2 class="text-sm uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold mb-3 flex items-center gap-2">
            <i class="pi pi-sitemap"></i> Drivers
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div v-for="(v, k) in drivers" :key="k"
                 class="flex items-center gap-3 p-4 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800">
                <div class="w-9 h-9 rounded-lg bg-indigo-500/10 text-indigo-500 flex items-center justify-center">
                    <i :class="['pi', driverIcons[k] || 'pi-cog']"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">{{ k }}</p>
                    <p class="font-mono text-sm truncate">{{ v }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Cache status + PHP limits + Laravel detail -->
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
        <div class="p-6 rounded-2xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800">
            <h3 class="font-semibold mb-4 flex items-center gap-2"><i class="pi pi-bolt text-indigo-500"></i>Cache status</h3>
            <ul class="space-y-2">
                <li v-for="(v, k) in cache_status" :key="k" class="flex items-center justify-between">
                    <span class="text-sm capitalize">{{ k }}</span>
                    <Tag :value="v ? 'cached' : 'not cached'" :severity="v ? 'success' : 'secondary'" />
                </li>
            </ul>
        </div>

        <div class="p-6 rounded-2xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800">
            <h3 class="font-semibold mb-4 flex items-center gap-2"><i class="pi pi-sliders-h text-indigo-500"></i>PHP limits</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Upload max</dt><dd class="font-mono">{{ php.upload_max_filesize }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">POST max</dt><dd class="font-mono">{{ php.post_max_size }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Memory limit</dt><dd class="font-mono">{{ php.memory_limit }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Execution time</dt><dd class="font-mono">{{ php.max_execution_time }}s</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Max uploads</dt><dd class="font-mono">{{ php.max_file_uploads }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Zend</dt><dd class="font-mono">{{ php.zend_version }}</dd></div>
            </dl>
        </div>

        <div class="p-6 rounded-2xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800">
            <h3 class="font-semibold mb-4 flex items-center gap-2"><i class="pi pi-box text-indigo-500"></i>Laravel runtime</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">App name</dt><dd class="font-mono truncate max-w-[60%]">{{ laravel.app_name }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Environment</dt><dd><Tag :value="String(laravel.environment)" :severity="laravel.environment === 'production' ? 'danger' : 'success'" /></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Debug</dt><dd><Tag :value="laravel.debug ? 'ON' : 'OFF'" :severity="laravel.debug ? 'warn' : 'success'" /></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Maintenance</dt><dd><Tag :value="laravel.maintenance ? 'ON' : 'OFF'" :severity="laravel.maintenance ? 'warn' : 'secondary'" /></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Timezone</dt><dd class="font-mono">{{ laravel.timezone }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Locale</dt><dd class="font-mono">{{ laravel.locale }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Composer</dt><dd class="font-mono">{{ laravel.composer_version }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Storage link</dt><dd><Tag :value="laravel.storage_linked ? 'linked' : 'missing'" :severity="laravel.storage_linked ? 'success' : 'warn'" /></dd></div>
            </dl>
        </div>
    </section>

    <!-- Host row -->
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
        <div class="p-6 rounded-2xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800">
            <h3 class="font-semibold mb-4 flex items-center gap-2"><i class="pi pi-server text-indigo-500"></i>Host</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">OS family</dt><dd class="font-mono">{{ system.os_family }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Hostname</dt><dd class="font-mono">{{ system.hostname }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Server software</dt><dd class="font-mono truncate max-w-[60%]">{{ system.server_software }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Document root</dt><dd class="font-mono text-xs truncate max-w-[60%]">{{ system.document_root }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">App URL</dt><dd class="font-mono text-xs truncate max-w-[60%]">{{ laravel.url }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">INI file</dt><dd class="font-mono text-xs truncate max-w-[60%]">{{ php.ini_loaded_file }}</dd></div>
            </dl>
        </div>

        <div class="p-6 rounded-2xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold flex items-center gap-2"><i class="pi pi-th-large text-indigo-500"></i>Loaded extensions
                    <Tag :value="String(extensions.length)" severity="secondary" />
                </h3>
                <button @click="extensionsOpen = !extensionsOpen" class="text-xs text-indigo-500 hover:underline">
                    {{ extensionsOpen ? 'Hide' : 'Show all' }}
                </button>
            </div>
            <div v-if="extensionsOpen" class="flex flex-wrap gap-1 max-h-64 overflow-y-auto">
                <span v-for="e in extensions" :key="e"
                      class="px-2 py-0.5 rounded-md text-xs font-mono bg-gray-100 dark:bg-dark-800 text-gray-700 dark:text-gray-300">
                    {{ e }}
                </span>
            </div>
            <div v-else class="flex flex-wrap gap-1">
                <span v-for="e in extensions.slice(0, 24)" :key="e"
                      class="px-2 py-0.5 rounded-md text-xs font-mono bg-gray-100 dark:bg-dark-800 text-gray-700 dark:text-gray-300">
                    {{ e }}
                </span>
                <span v-if="extensions.length > 24" class="px-2 py-0.5 rounded-md text-xs text-gray-400">
                    +{{ extensions.length - 24 }} more
                </span>
            </div>
        </div>
    </section>
</template>
