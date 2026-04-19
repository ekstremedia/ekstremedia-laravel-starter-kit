<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
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
const { t } = useI18n();

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
const addingCustomer = ref(false);
const removingCustomerRequest = ref(false);

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
    addingCustomer.value = true;
    router.post(`/admin/users/${props.user.id}/customers`, {
        customer_id: selectedCustomerId.value,
        notify: notifyOnAdd.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { addCustomerDialog.value = false; },
        onFinish: () => { addingCustomer.value = false; },
    });
}

function openRemoveDialog(customer: CustomerItem) {
    removingCustomer.value = customer;
    notifyOnRemove.value = true;
    removeDialog.value = true;
}

function confirmRemove() {
    if (!removingCustomer.value) return;
    removingCustomerRequest.value = true;
    router.delete(`/admin/users/${props.user.id}/customers/${removingCustomer.value.id}`, {
        data: { notify: notifyOnRemove.value },
        preserveScroll: true,
        onSuccess: () => {
            removeDialog.value = false;
            removingCustomer.value = null;
        },
        onFinish: () => { removingCustomerRequest.value = false; },
    });
}
</script>

<template>
    <div>
    <Head :title="`Edit ${user.email} · Admin`" />
    <ConfirmDialog />

    <!-- Add customer dialog -->
    <Dialog v-model:visible="addCustomerDialog" :header="t('admin.users.add_to_customer')" modal :style="{ width: '28rem' }">
        <div class="space-y-4">
            <div>
                <label class="block text-sm mb-1">{{ t('admin.customers.title') }}</label>
                <Select
                    v-model="selectedCustomerId"
                    :options="availableCustomers()"
                    optionLabel="name"
                    optionValue="id"
                    :placeholder="t('admin.users.select_customer')"
                    class="w-full"
                />
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="notifyOnAdd" :binary="true" inputId="notifyAdd" />
                <label for="notifyAdd" class="text-sm">{{ t('admin.users.notify_user') }}</label>
            </div>
        </div>
        <template #footer>
            <Button :label="t('common.cancel')" severity="secondary" @click="addCustomerDialog = false" />
            <Button :label="t('common.add')" icon="pi pi-plus" :disabled="!selectedCustomerId" :loading="addingCustomer" @click="confirmAdd" />
        </template>
    </Dialog>

    <!-- Remove customer dialog -->
    <Dialog v-model:visible="removeDialog" :header="t('admin.users.remove_from_customer')" modal :style="{ width: '28rem' }">
        <p class="text-sm text-gray-500 mb-4">
            Remove <strong>{{ user.email }}</strong> from <strong>{{ removingCustomer?.name }}</strong>?
        </p>
        <div class="flex items-center gap-2">
            <Checkbox v-model="notifyOnRemove" :binary="true" inputId="notifyRemove" />
            <label for="notifyRemove" class="text-sm">{{ t('admin.users.notify_user') }}</label>
        </div>
        <template #footer>
            <Button :label="t('common.cancel')" severity="secondary" @click="removeDialog = false" />
            <Button :label="t('common.delete')" icon="pi pi-times" severity="danger" :loading="removingCustomerRequest" @click="confirmRemove" />
        </template>
    </Dialog>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">{{ t('admin.users.edit_user') }}</h1>
        <Link href="/admin/users" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">{{ t('common.back') }}</Link>
    </div>

    <div class="grid gap-6" :class="tenancy_enabled ? 'lg:grid-cols-2' : 'max-w-2xl'">
        <form @submit.prevent="submit" class="bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">{{ t('admin.users.first_name') }}</label>
                    <InputText v-model="form.first_name" class="w-full" />
                    <p v-if="form.errors.first_name" class="text-xs text-red-500 mt-1">{{ form.errors.first_name }}</p>
                </div>
                <div>
                    <label class="block text-sm mb-1">{{ t('admin.users.last_name') }}</label>
                    <InputText v-model="form.last_name" class="w-full" />
                    <p v-if="form.errors.last_name" class="text-xs text-red-500 mt-1">{{ form.errors.last_name }}</p>
                </div>
            </div>
            <div>
                <label class="block text-sm mb-1">{{ t('admin.users.email') }}</label>
                <InputText v-model="form.email" type="email" class="w-full" />
                <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">{{ t('admin.users.new_password') }}</label>
                    <Password v-model="form.password" toggleMask :feedback="false" class="w-full" inputClass="w-full" />
                    <p v-if="form.errors.password" class="text-xs text-red-500 mt-1">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label class="block text-sm mb-1">{{ t('admin.users.confirm_password') }}</label>
                    <Password v-model="form.password_confirmation" toggleMask :feedback="false" class="w-full" inputClass="w-full" />
                </div>
            </div>
            <div>
                <label class="block text-sm mb-1">{{ t('admin.users.roles') }}</label>
                <MultiSelect v-model="form.roles" :options="roles" optionLabel="name" optionValue="name" placeholder="Select roles" class="w-full" />
            </div>
            <div class="flex gap-2">
                <Button type="submit" :label="t('common.save')" icon="pi pi-check" :loading="form.processing" />
                <Link href="/admin/users"><Button :label="t('common.cancel')" severity="secondary" /></Link>
            </div>
        </form>

        <!-- Customer memberships panel -->
        <section v-if="tenancy_enabled" class="bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-medium">{{ t('admin.users.customer_memberships') }} ({{ user.customers.length }})</h2>
                <Button :label="t('common.add')" icon="pi pi-plus" size="small" @click="openAddDialog" :disabled="availableCustomers().length === 0" />
            </div>

            <ul v-if="user.customers.length" class="divide-y divide-gray-100 dark:divide-dark-800">
                <li v-for="customer in user.customers" :key="customer.id" class="flex items-center justify-between py-2">
                    <div>
                        <Link :href="`/admin/customers/${customer.id}/edit`" class="text-sm font-medium hover:text-indigo-600">
                            {{ customer.name }}
                        </Link>
                        <p class="text-xs text-gray-500">/c/{{ customer.slug }}</p>
                    </div>
                    <Button icon="pi pi-times" severity="secondary" size="small" text :aria-label="`Remove ${customer.name}`" @click="openRemoveDialog(customer)" />
                </li>
            </ul>
            <p v-else class="text-sm text-gray-400 italic">{{ t('admin.users.not_member') }}</p>
        </section>
    </div>
    </div>
</template>
