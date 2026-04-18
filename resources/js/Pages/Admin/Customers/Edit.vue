<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';

defineOptions({ layout: AdminLayout });

interface Member { id: number; email: string; full_name: string }
interface CustomerData {
    id: number;
    slug: string;
    name: string;
    status: 'active' | 'suspended';
    users: Member[];
}

const props = defineProps<{ customer: CustomerData }>();

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Suspended', value: 'suspended' },
];

const form = useForm({
    name: props.customer.name,
    status: props.customer.status,
});

function save() {
    form.put(`/admin/customers/${props.customer.id}`);
}

const memberForm = useForm({ email: '' });

function attach() {
    memberForm.post(`/admin/customers/${props.customer.id}/members`, {
        preserveScroll: true,
        onSuccess: () => memberForm.reset('email'),
    });
}

const confirm = useConfirm();

function detach(member: Member) {
    confirm.require({
        message: `Remove ${member.email} from ${props.customer.name}?`,
        header: 'Remove member',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(`/admin/customers/${props.customer.id}/members/${member.id}`, { preserveScroll: true }),
    });
}
</script>

<template>
    <Head :title="`${customer.name} · Admin`" />
    <ConfirmDialog />

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">{{ customer.name }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                <code class="text-xs bg-gray-100 dark:bg-dark-800 px-1.5 py-0.5 rounded">/c/{{ customer.slug }}</code>
                ·
                <Tag :value="customer.status" :severity="customer.status === 'active' ? 'success' : 'warn'" />
            </p>
        </div>
        <Link href="/admin/customers" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">← Back</Link>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl p-6">
            <h2 class="text-lg font-medium mb-4">Settings</h2>
            <form @submit.prevent="save" class="space-y-4">
                <div>
                    <label class="block text-sm mb-1">Name</label>
                    <InputText v-model="form.name" class="w-full" />
                    <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm mb-1">Status</label>
                    <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full" />
                    <p class="text-xs text-gray-500 mt-1">Suspended customers reject all member traffic.</p>
                </div>
                <div>
                    <Button type="submit" label="Save" icon="pi pi-check" :loading="form.processing" />
                </div>
            </form>
        </section>

        <section class="bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl p-6">
            <h2 class="text-lg font-medium mb-4">Members ({{ customer.users.length }})</h2>

            <form @submit.prevent="attach" class="flex gap-2 mb-4">
                <InputText v-model="memberForm.email" type="email" class="flex-1" placeholder="existing-user@example.com" />
                <Button type="submit" label="Add" icon="pi pi-user-plus" :loading="memberForm.processing" />
            </form>
            <p v-if="memberForm.errors.email" class="text-xs text-red-500 -mt-2 mb-4">{{ memberForm.errors.email }}</p>

            <ul v-if="customer.users.length" class="divide-y divide-gray-100 dark:divide-dark-800">
                <li v-for="member in customer.users" :key="member.id" class="flex items-center justify-between py-2">
                    <div>
                        <p class="text-sm font-medium">{{ member.full_name }}</p>
                        <p class="text-xs text-gray-500">{{ member.email }}</p>
                    </div>
                    <Button icon="pi pi-times" severity="secondary" size="small" text @click="detach(member)" />
                </li>
            </ul>
            <p v-else class="text-sm text-gray-500">No members yet. Add an existing user by email above.</p>
        </section>
    </div>
</template>
