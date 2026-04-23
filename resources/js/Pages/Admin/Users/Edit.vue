<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '@/Layouts/CommandLayout.vue';
import MultiSelect from 'primevue/multiselect';
import Password from 'primevue/password';
import ConfirmDialog from 'primevue/confirmdialog';
import CommandDialog from '@/Components/Command/Dialog.vue';
import Field from '@/Components/Command/Field.vue';
import CmdButton from '@/Components/Command/Button.vue';
import Toggle from '@/Components/Command/Toggle.vue';
import Icon from '@/Components/Command/Icon.vue';

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

const addCustomerDialog = ref(false);
const selectedCustomerIds = ref<number[]>([]);
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
    selectedCustomerIds.value = [];
    notifyOnAdd.value = true;
    addCustomerDialog.value = true;
}

function confirmAdd() {
    if (!selectedCustomerIds.value.length) return;
    addingCustomer.value = true;
    router.post(`/admin/users/${props.user.id}/customers`, {
        customer_ids: selectedCustomerIds.value,
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
    <div :style="{ padding: '24px 32px', maxWidth: '1100px', margin: '0 auto' }">
        <Head :title="`Edit ${user.email} · Admin`" />
        <ConfirmDialog />

        <!-- Add customer dialog -->
        <CommandDialog
            v-model:visible="addCustomerDialog"
            :title="t('admin.users.add_to_customer')"
            width="460px"
        >
            <div :style="{ display: 'flex', flexDirection: 'column', gap: '14px' }">
                <div>
                    <label
                        class="cmd-mono cmd-uc"
                        :style="{ display: 'block', fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '6px', letterSpacing: '0.06em', fontWeight: 500 }"
                    >{{ t('admin.customers.title') }}</label>
                    <MultiSelect
                        v-model="selectedCustomerIds"
                        :options="availableCustomers()"
                        optionLabel="name"
                        optionValue="id"
                        :placeholder="t('admin.users.select_customer')"
                        :filter="true"
                        :filterPlaceholder="t('common.search')"
                        display="chip"
                        class="w-full"
                    />
                </div>
                <label :style="{ display: 'flex', alignItems: 'center', gap: '10px', fontSize: '12.5px', color: 'var(--fg)', cursor: 'pointer' }">
                    <Toggle v-model="notifyOnAdd" />
                    <span>{{ t('admin.users.notify_user') }}</span>
                </label>
            </div>
            <template #footer>
                <CmdButton variant="ghost" size="sm" @click="addCustomerDialog = false">
                    {{ t('common.cancel') }}
                </CmdButton>
                <CmdButton
                    variant="primary"
                    size="sm"
                    :disabled="!selectedCustomerIds.length"
                    :loading="addingCustomer"
                    @click="confirmAdd"
                >
                    {{ t('common.add') }}
                </CmdButton>
            </template>
        </CommandDialog>

        <!-- Remove customer dialog -->
        <CommandDialog
            v-model:visible="removeDialog"
            :title="t('admin.users.remove_from_customer')"
            width="440px"
        >
            <p :style="{ margin: '0 0 14px', fontSize: '13px', color: 'var(--fg-dim)', lineHeight: 1.5 }">
                Remove <strong :style="{ color: 'var(--fg)' }">{{ user.email }}</strong>
                from <strong :style="{ color: 'var(--fg)' }">{{ removingCustomer?.name }}</strong>?
            </p>
            <label :style="{ display: 'flex', alignItems: 'center', gap: '10px', fontSize: '12.5px', color: 'var(--fg)', cursor: 'pointer' }">
                <Toggle v-model="notifyOnRemove" />
                <span>{{ t('admin.users.notify_user') }}</span>
            </label>
            <template #footer>
                <CmdButton variant="ghost" size="sm" @click="removeDialog = false">
                    {{ t('common.cancel') }}
                </CmdButton>
                <CmdButton
                    variant="danger"
                    size="sm"
                    :loading="removingCustomerRequest"
                    @click="confirmRemove"
                >
                    {{ t('common.delete') }}
                </CmdButton>
            </template>
        </CommandDialog>

        <div :style="{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '24px' }">
            <h1 :style="{ margin: 0, fontSize: '24px', fontWeight: 600, letterSpacing: '-0.02em', color: 'var(--fg)' }">
                {{ t('admin.users.edit_user') }}
            </h1>
            <Link
                href="/admin/users"
                :style="{ fontSize: '12px', color: 'var(--fg-dim)', textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: '4px' }"
            >{{ t('common.back') }}</Link>
        </div>

        <div
            :style="{
                display: 'grid',
                gridTemplateColumns: tenancy_enabled ? 'minmax(0, 1fr) minmax(0, 1fr)' : 'minmax(0, 720px)',
                gap: '20px',
            }"
        >
            <form
                @submit.prevent="submit"
                class="cmd-card"
                :style="{ padding: '20px', display: 'flex', flexDirection: 'column', gap: '14px' }"
            >
                <div :style="{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', gap: '12px' }">
                    <Field
                        v-model="form.first_name"
                        :label="t('admin.users.first_name')"
                        :error="form.errors.first_name"
                    />
                    <Field
                        v-model="form.last_name"
                        :label="t('admin.users.last_name')"
                        :error="form.errors.last_name"
                    />
                </div>
                <Field
                    v-model="form.email"
                    type="email"
                    :label="t('admin.users.email')"
                    :error="form.errors.email"
                />
                <div :style="{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', gap: '12px' }">
                    <div>
                        <label
                            class="cmd-mono cmd-uc"
                            :style="{ display: 'block', fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '6px', letterSpacing: '0.06em', fontWeight: 500 }"
                        >{{ t('admin.users.new_password') }}</label>
                        <Password v-model="form.password" toggleMask :feedback="false" class="w-full" inputClass="w-full" />
                        <p v-if="form.errors.password" :style="{ color: 'var(--danger)', fontSize: '11px', marginTop: '4px' }">{{ form.errors.password }}</p>
                    </div>
                    <div>
                        <label
                            class="cmd-mono cmd-uc"
                            :style="{ display: 'block', fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '6px', letterSpacing: '0.06em', fontWeight: 500 }"
                        >{{ t('admin.users.confirm_password') }}</label>
                        <Password v-model="form.password_confirmation" toggleMask :feedback="false" class="w-full" inputClass="w-full" />
                    </div>
                </div>
                <div>
                    <label
                        class="cmd-mono cmd-uc"
                        :style="{ display: 'block', fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '6px', letterSpacing: '0.06em', fontWeight: 500 }"
                    >{{ t('admin.users.roles') }}</label>
                    <MultiSelect
                        v-model="form.roles"
                        :options="roles"
                        optionLabel="name"
                        optionValue="name"
                        :placeholder="t('admin.users.select_roles')"
                        class="w-full"
                    />
                </div>
                <div :style="{ display: 'flex', gap: '8px', marginTop: '4px' }">
                    <CmdButton type="submit" variant="primary" size="md" :loading="form.processing">
                        <template #icon>
                            <Icon name="check" :size="13" />
                        </template>
                        {{ t('common.save') }}
                    </CmdButton>
                    <Link href="/admin/users" :style="{ textDecoration: 'none' }">
                        <CmdButton variant="ghost" size="md">
                            {{ t('common.cancel') }}
                        </CmdButton>
                    </Link>
                </div>
            </form>

            <section
                v-if="tenancy_enabled"
                class="cmd-card"
                :style="{ padding: '20px' }"
            >
                <div :style="{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '14px' }">
                    <h2 :style="{ margin: 0, fontSize: '14px', fontWeight: 600, color: 'var(--fg)' }">
                        {{ t('admin.users.customer_memberships') }} ({{ user.customers.length }})
                    </h2>
                    <CmdButton
                        variant="primary"
                        size="sm"
                        :disabled="availableCustomers().length === 0"
                        @click="openAddDialog"
                    >
                        {{ t('common.add') }}
                    </CmdButton>
                </div>

                <ul
                    v-if="user.customers.length"
                    :style="{ listStyle: 'none', padding: 0, margin: 0 }"
                >
                    <li
                        v-for="(customer, i) in user.customers"
                        :key="customer.id"
                        :style="{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            padding: '10px 0',
                            borderTop: i === 0 ? 'none' : '1px solid var(--border)',
                        }"
                    >
                        <div>
                            <Link
                                :href="`/admin/customers/${customer.id}/edit`"
                                :style="{ fontSize: '13px', fontWeight: 500, color: 'var(--fg)', textDecoration: 'none' }"
                            >
                                {{ customer.name }}
                            </Link>
                            <p class="cmd-mono" :style="{ margin: '2px 0 0', fontSize: '11px', color: 'var(--fg-mute)' }">
                                /c/{{ customer.slug }}
                            </p>
                        </div>
                        <CmdButton
                            variant="ghost"
                            size="sm"
                            :aria-label="`Remove ${customer.name}`"
                            @click="openRemoveDialog(customer)"
                        >
                            {{ t('common.remove') }}
                        </CmdButton>
                    </li>
                </ul>
                <p
                    v-else
                    :style="{ fontSize: '12.5px', color: 'var(--fg-mute)', fontStyle: 'italic', margin: 0 }"
                >{{ t('admin.users.not_member') }}</p>
            </section>
        </div>
    </div>
</template>
