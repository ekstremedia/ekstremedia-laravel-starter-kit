<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Button from 'primevue/button';

defineOptions({ layout: AdminLayout });

interface Props {
    role: { id: number; name: string; permissions: string[] } | null;
    permissions: { id: number; name: string }[];
}
const props = defineProps<Props>();

const form = useForm({
    name: props.role?.name ?? '',
    permissions: props.role?.permissions ? [...props.role.permissions] : [] as string[],
});

function submit() {
    if (props.role) {
        form.put(`/admin/roles/${props.role.id}`);
    } else {
        form.post('/admin/roles');
    }
}
</script>

<template>
    <Head :title="(role ? 'Edit role' : 'New role') + ' · Admin'" />
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">{{ role ? 'Edit role' : 'New role' }}</h1>
        <Link href="/admin/roles" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">← Back</Link>
    </div>

    <form @submit.prevent="submit" class="max-w-2xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl p-6 space-y-4">
        <div>
            <label class="block text-sm mb-1">Name</label>
            <InputText v-model="form.name" class="w-full" />
            <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
        </div>
        <div>
            <label class="block text-sm mb-1">Permissions</label>
            <MultiSelect v-model="form.permissions" :options="permissions" optionLabel="name" optionValue="name" placeholder="Select permissions" class="w-full" :filter="true" />
        </div>
        <div class="flex gap-2">
            <Button type="submit" label="Save" icon="pi pi-check" :loading="form.processing" />
            <Link href="/admin/roles"><Button label="Cancel" severity="secondary" /></Link>
        </div>
    </form>
</template>
