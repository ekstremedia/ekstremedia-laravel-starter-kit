<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import MultiSelect from 'primevue/multiselect';
import Button from 'primevue/button';

defineOptions({ layout: AdminLayout });

interface Props { roles: { id: number; name: string }[] }
defineProps<Props>();

const { t } = useI18n();

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [] as string[],
});

function submit() {
    form.post('/admin/users');
}
</script>

<template>
    <Head title="New user · Admin" />
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">{{ t('admin.users.new_user') }}</h1>
        <Link href="/admin/users" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">{{ t('common.back') }}</Link>
    </div>

    <form @submit.prevent="submit" class="max-w-2xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl p-6 space-y-4">
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
                <label class="block text-sm mb-1">{{ t('admin.users.password') }}</label>
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
            <Button type="submit" :label="t('admin.users.create_user')" icon="pi pi-check" :loading="form.processing" />
            <Link href="/admin/users"><Button :label="t('common.cancel')" severity="secondary" /></Link>
        </div>
    </form>
</template>
