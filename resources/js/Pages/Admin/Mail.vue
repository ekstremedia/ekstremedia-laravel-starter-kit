<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Password from 'primevue/password';
import ToggleSwitch from 'primevue/toggleswitch';
import Button from 'primevue/button';
import Select from 'primevue/select';

defineOptions({ layout: AdminLayout });

interface Props {
    settings: {
        mailer: string; host: string | null; port: number | null; encryption: string | null;
        username: string | null; password: string | null; from_address: string | null; from_name: string | null;
        enabled: boolean;
    };
}
const props = defineProps<Props>();

const form = useForm({
    mailer: props.settings.mailer,
    host: props.settings.host ?? '',
    port: props.settings.port,
    encryption: props.settings.encryption,
    username: props.settings.username ?? '',
    password: '',
    from_address: props.settings.from_address ?? '',
    from_name: props.settings.from_name ?? '',
    enabled: props.settings.enabled,
});

const encryptionOptions = [
    { label: 'None', value: null },
    { label: 'TLS', value: 'tls' },
    { label: 'SSL', value: 'ssl' },
];

function save() {
    form.patch('/admin/mail', { preserveScroll: true });
}
function sendTest() {
    router.post('/admin/mail/test', {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Mail Settings · Admin" />
    <h1 class="text-2xl font-semibold mb-6">Mail Settings</h1>

    <form @submit.prevent="save" class="max-w-3xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl p-6 space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">Mailer</label>
                <InputText v-model="form.mailer" class="w-full" />
            </div>
            <div class="flex items-center gap-3">
                <label class="text-sm">Enabled</label>
                <ToggleSwitch v-model="form.enabled" />
            </div>
        </div>
        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2">
                <label class="block text-sm mb-1">Host</label>
                <InputText v-model="form.host" class="w-full" />
            </div>
            <div>
                <label class="block text-sm mb-1">Port</label>
                <InputNumber v-model="form.port" class="w-full" :useGrouping="false" />
            </div>
        </div>
        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm mb-1">Encryption</label>
                <Select v-model="form.encryption" :options="encryptionOptions" optionLabel="label" optionValue="value" class="w-full" />
            </div>
            <div>
                <label class="block text-sm mb-1">Username</label>
                <InputText v-model="form.username" class="w-full" />
            </div>
            <div>
                <label class="block text-sm mb-1">Password</label>
                <Password v-model="form.password" toggleMask :feedback="false" class="w-full" inputClass="w-full" placeholder="(unchanged)" />
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">From address</label>
                <InputText v-model="form.from_address" class="w-full" />
            </div>
            <div>
                <label class="block text-sm mb-1">From name</label>
                <InputText v-model="form.from_name" class="w-full" />
            </div>
        </div>
        <div class="flex gap-2 pt-2">
            <Button type="submit" label="Save" icon="pi pi-check" :loading="form.processing" />
            <Button type="button" label="Send test email to me" icon="pi pi-send" severity="secondary" @click="sendTest" />
        </div>
    </form>
</template>
