<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import MultiSelect from 'primevue/multiselect';
import Button from 'primevue/button';

defineOptions({ layout: AdminLayout });

interface Props {
    user: { id: number; first_name: string; last_name: string; email: string; roles: string[] };
    roles: { id: number; name: string }[];
}
const props = defineProps<Props>();

const form = useForm({
    first_name: props.user.first_name,
    last_name: props.user.last_name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    roles: [...props.user.roles],
});

function submit() {
    form.put(`/admin/users/${props.user.id}`);
}
</script>

<template>
    <Head :title="`Edit ${user.email} · Admin`" />
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">Edit user</h1>
        <Link href="/admin/users" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">← Back</Link>
    </div>

    <form @submit.prevent="submit" class="max-w-2xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl p-6 space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">First name</label>
                <InputText v-model="form.first_name" class="w-full" />
                <p v-if="form.errors.first_name" class="text-xs text-red-500 mt-1">{{ form.errors.first_name }}</p>
            </div>
            <div>
                <label class="block text-sm mb-1">Last name</label>
                <InputText v-model="form.last_name" class="w-full" />
                <p v-if="form.errors.last_name" class="text-xs text-red-500 mt-1">{{ form.errors.last_name }}</p>
            </div>
        </div>
        <div>
            <label class="block text-sm mb-1">Email</label>
            <InputText v-model="form.email" type="email" class="w-full" />
            <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">New password (optional)</label>
                <Password v-model="form.password" toggleMask :feedback="false" class="w-full" inputClass="w-full" />
                <p v-if="form.errors.password" class="text-xs text-red-500 mt-1">{{ form.errors.password }}</p>
            </div>
            <div>
                <label class="block text-sm mb-1">Confirm password</label>
                <Password v-model="form.password_confirmation" toggleMask :feedback="false" class="w-full" inputClass="w-full" />
            </div>
        </div>
        <div>
            <label class="block text-sm mb-1">Roles</label>
            <MultiSelect v-model="form.roles" :options="roles" optionLabel="name" optionValue="name" placeholder="Select roles" class="w-full" />
        </div>
        <div class="flex gap-2">
            <Button type="submit" label="Save" icon="pi pi-check" :loading="form.processing" />
            <Link href="/admin/users"><Button label="Cancel" severity="secondary" /></Link>
        </div>
    </form>
</template>
