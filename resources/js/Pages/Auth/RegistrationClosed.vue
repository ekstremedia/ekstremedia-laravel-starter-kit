<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Registration closed" />
    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-dark-950 text-gray-900 dark:text-gray-100 p-6">
        <div class="max-w-md text-center">
            <i class="pi pi-lock text-5xl text-amber-500 mb-4"></i>
            <h1 class="text-2xl font-semibold mb-2">Registration is closed</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                New accounts can't be created right now.
            </p>
            <Link href="/login" class="text-indigo-500 hover:underline text-sm">← Back to sign in</Link>
        </div>
    </div>
</template>
