<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

defineOptions({ layout: AdminLayout });

const form = useForm({
    name: '',
    slug: '',
});

function submit() {
    form.post('/admin/customers');
}

function slugify(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 63);
}

// Live preview that mirrors the server-side fallback (`Str::slug($name)`): if
// the user hasn't typed a slug, show what we'd derive from the current name.
const effectiveSlug = computed(() => form.slug || slugify(form.name));

function onNameBlur() {
    if (!form.slug && form.name) {
        form.slug = slugify(form.name);
    }
}
</script>

<template>
    <Head title="New customer · Admin" />
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">New customer</h1>
        <Link href="/admin/customers" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">← Back</Link>
    </div>

    <form @submit.prevent="submit"
          class="max-w-2xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl p-6 space-y-4">
        <div>
            <label class="block text-sm mb-1">Name</label>
            <InputText v-model="form.name" class="w-full" @blur="onNameBlur" placeholder="Acme Corp" />
            <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
        </div>
        <div>
            <label class="block text-sm mb-1">Slug</label>
            <InputText v-model="form.slug" class="w-full" placeholder="acme" />
            <p class="text-xs text-gray-500 dark:text-dark-400 mt-1">
                Used in the URL (/c/<strong>{{ effectiveSlug || 'slug' }}</strong>). Lowercase letters, digits and hyphens.
            </p>
            <p v-if="form.errors.slug" class="text-xs text-red-500 mt-1">{{ form.errors.slug }}</p>
        </div>
        <div class="flex gap-2 pt-2">
            <Button type="submit" label="Create customer" icon="pi pi-check" :loading="form.processing" />
            <Link href="/admin/customers"><Button label="Cancel" severity="secondary" /></Link>
        </div>
    </form>
</template>
