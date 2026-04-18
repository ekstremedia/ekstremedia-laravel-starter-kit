<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Checkbox from 'primevue/checkbox';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';

defineOptions({ layout: AdminLayout });

interface CustomerItem { id: number; name: string; slug: string }
interface Props {
    user: {
        id: number; first_name: string; last_name: string; email: string;
        roles: string[];
        customers: CustomerItem[];
    };
    roles: { id: number; name: string }[];
    tenancy_enabled: boolean;
    all_customers: CustomerItem[];
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

// ── Customer membership management ──────────────────────────────
const confirm = useConfirm();
const addCustomerDialog = ref(false);
const selectedCustomerId = ref<number | null>(null);
const notifyOnAdd = ref(true);
const notifyOnRemove = ref(true);
const removeDialog = ref(false);
const removingCustomer = ref<CustomerItem | null>(null);

const availableCustomers = () => {
    const currentIds = new Set(props.user.customers.map(c => c.id));
    return props.all_customers.filter(c => !currentIds.has(c.id));
};

function openAddDialog() {
    selectedCustomerId.value = null;
    notifyOnAdd.value = true;
    addCustomerDialog.value = true;
}

function confirmAdd() {
    if (!selectedCustomerId.value) return;
    router.post(`/admin/users/${props.user.id}/customers`, {
        customer_id: selectedCustomerId.value,
        notify: notifyOnAdd.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            addCustomerDialog.value = false;
        },
    });
}

function openRemoveDialog(customer: CustomerItem) {
    removingCustomer.value = customer;
    notifyOnRemove.value = true;
    removeDialog.value = true;
}

function confirmRemove() {
    if (!removingCustomer.value) return;
    router.delete(`/admin/users/${props.user.id}/customers/${removingCustomer.value.id}`, {
        data: { notify: notifyOnRemove.value },
        preserveScroll: true,
        onSuccess: () => {
            removeDialog.value = false;
            removingCustomer.value = null;
        },
    });
}
</script>

<template>
    <Head :title="`Edit ${user.email} · Admin`" />
    <ConfirmDialog />

    <!-- Add customer dialog -->
    <Dialog v-model:visible="addCustomerDialog" header="Add to customer" modal :style="{ width: '28rem' }">
        <div class="space-y-4">
            <div>
                <label class="block text-sm mb-1">Customer</label>
                <Select
                    v-model="selectedCustomerId"
                    :options="availableCustomers()"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Select a customer"
                    class="w-full"
                />
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="notifyOnAdd" :binary="true" inputId="notifyAdd" />
                <label for="notifyAdd" class="text-sm">Notify user by email</label>
            </div>
        </div>
        <template #footer>
            <Button label="Cancel" severity="secondary" @click="addCustomerDialog = false" />
            <Button label="Add" icon="pi pi-plus" :disabled="!selectedCustomerId" @click="confirmAdd" />
        </template>
    </Dialog>

    <!-- Remove customer dialog -->
    <Dialog v-model:visible="removeDialog" header="Remove from customer" modal :style="{ width: '28rem' }">
        <p class="text-sm text-gray-500 mb-4">
            Remove <strong>{{ user.email }}</strong> from <strong>{{ removingCustomer?.name }}</strong>?
        </p>
        <div class="flex items-center gap-2">
            <Checkbox v-model="notifyOnRemove" :binary="true" inputId="notifyRemove" />
            <label for="notifyRemove" class="text-sm">Notify user by email</label>
        </div>
        <template #footer>
            <Button label="Cancel" severity="secondary" @click="removeDialog = false" />
            <Button label="Remove" icon="pi pi-times" severity="danger" @click="confirmRemove" />
        </template>
    </Dialog>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">Edit user</h1>
        <Link href="/admin/users" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">← Back</Link>
    </div>

    <div class="grid gap-6" :class="tenancy_enabled ? 'lg:grid-cols-2' : 'max-w-2xl'">
        <form @submit.prevent="submit" class="bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

        <!-- Customer memberships panel -->
        <section v-if="tenancy_enabled" class="bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-medium">Customer memberships ({{ user.customers.length }})</h2>
                <Button label="Add" icon="pi pi-plus" size="small" @click="openAddDialog" :disabled="availableCustomers().length === 0" />
            </div>

            <ul v-if="user.customers.length" class="divide-y divide-gray-100 dark:divide-dark-800">
                <li v-for="customer in user.customers" :key="customer.id" class="flex items-center justify-between py-2">
                    <div>
                        <Link :href="`/admin/customers/${customer.id}/edit`" class="text-sm font-medium hover:text-indigo-600">
                            {{ customer.name }}
                        </Link>
                        <p class="text-xs text-gray-500">/c/{{ customer.slug }}</p>
                    </div>
                    <Button icon="pi pi-times" severity="secondary" size="small" text @click="openRemoveDialog(customer)" />
                </li>
            </ul>
            <p v-else class="text-sm text-gray-400 italic">Not a member of any customer.</p>
        </section>
    </div>
</template>
