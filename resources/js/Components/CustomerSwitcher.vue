<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Customer, PageProps } from '@/types';

/**
 * Customer / tenant chip in the navbar.
 *  - 0 customers → hidden
 *  - 1 customer  → static badge showing the name (no dropdown)
 *  - N customers → button + dropdown to switch; current customer is highlighted
 *
 * Clicking a customer routes to `/c/{slug}/dashboard` which triggers
 * InitializeTenancyByPath to swap the schema server-side.
 */
const { t } = useI18n();
const page = usePage<PageProps>();

const current = computed<Customer | null>(() => page.props.customer ?? null);
const list = computed<Customer[]>(() => page.props.customers ?? []);

const open = ref(false);
const rootRef = ref<HTMLElement | null>(null);

function toggle() {
    if (list.value.length <= 1) return;
    open.value = !open.value;
}

function onDocClick(e: MouseEvent) {
    if (!rootRef.value) return;
    if (!rootRef.value.contains(e.target as Node)) open.value = false;
}

onMounted(() => document.addEventListener('click', onDocClick));
onUnmounted(() => document.removeEventListener('click', onDocClick));

// Show nothing at all when tenancy is off, or the user has no memberships
// (prevents a bare chip on the welcome page).
const visible = computed(() => Boolean(page.props.tenancy?.enabled) && list.value.length > 0);
const hasMany = computed(() => list.value.length > 1);

function urlFor(c: Customer): string {
    return `/c/${c.slug}/dashboard`;
}
</script>

<template>
    <div v-if="visible" ref="rootRef" class="relative">
        <button
            type="button"
            :disabled="!hasMany"
            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-default disabled:opacity-90 dark:border-dark-700 dark:bg-dark-900 dark:text-slate-200 dark:hover:bg-dark-800"
            @click="toggle"
        >
            <i class="pi pi-building text-indigo-500" />
            <span class="max-w-[10rem] truncate">
                {{ current?.name ?? t('nav.pick_customer') }}
            </span>
            <i v-if="hasMany" class="pi pi-chevron-down text-xs text-slate-400" />
        </button>

        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open && hasMany"
                class="absolute right-0 z-30 mt-2 max-h-80 w-64 overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-dark-700 dark:bg-dark-900"
            >
                <div class="px-3 py-1.5 text-xs uppercase text-slate-500 dark:text-slate-400">
                    {{ t('nav.customers_header') }}
                </div>
                <Link
                    v-for="c in list"
                    :key="c.id"
                    :href="urlFor(c)"
                    class="flex items-center justify-between gap-2 px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-dark-800"
                    :class="c.id === current?.id
                        ? 'text-indigo-700 dark:text-indigo-300'
                        : 'text-slate-700 dark:text-slate-200'"
                    @click="open = false"
                >
                    <span class="flex items-center gap-2 truncate">
                        <i class="pi pi-building text-xs" />
                        <span class="truncate">{{ c.name }}</span>
                    </span>
                    <i v-if="c.id === current?.id" class="pi pi-check text-indigo-500" />
                </Link>
            </div>
        </Transition>
    </div>
</template>
