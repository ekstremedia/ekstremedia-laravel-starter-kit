<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { formatDateTime } from '@/composables/useDateTime';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';

defineOptions({ layout: AdminLayout });

interface ActivityItem { id: number; log_name: string | null; description: string; event: string | null; created_at: string }
interface CustomerItem { id: number; name: string; slug: string }
interface Props {
    user: {
        id: number; first_name: string; last_name: string; full_name: string; email: string;
        email_verified_at: string | null; banned_at: string | null; banned_reason: string | null;
        last_login_at: string | null; created_at: string; two_factor_enabled: boolean;
        roles: string[]; avatar_url: string | null; avatar_thumb_url: string | null;
        unread_notifications_count: number;
        customers: CustomerItem[];
    };
    activity: ActivityItem[];
    tenancy_enabled: boolean;
}
const props = defineProps<Props>();
const { t } = useI18n();

const isAdmin = props.user.roles.includes('Admin');
const confirm = useConfirm();

const banDialog = ref(false);
const notifyDialog = ref(false);

const banForm = useForm({ reason: '' });
const notifyForm = useForm({ message: '' });

function action(path: string) {
    router.post(`/admin/users/${props.user.id}/${path}`, {}, { preserveScroll: true });
}

function confirmBan() {
    banDialog.value = true;
}

function submitBan() {
    banForm.post(`/admin/users/${props.user.id}/ban`, {
        preserveScroll: true,
        onSuccess: () => {
            banDialog.value = false;
            banForm.reset();
        },
    });
}

function submitNotify() {
    notifyForm.post(`/admin/users/${props.user.id}/notify-test`, {
        preserveScroll: true,
        onSuccess: () => {
            notifyDialog.value = false;
            notifyForm.reset();
        },
    });
}

function confirmDestructive(message: string, path: string) {
    confirm.require({
        message, header: 'Confirm', icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger', accept: () => action(path),
    });
}

function destroy() {
    confirm.require({
        message: `Permanently delete ${props.user.email}?`,
        header: 'Delete user', icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(`/admin/users/${props.user.id}`),
    });
}

function formatDate(iso: string | null) {
    return formatDateTime(iso);
}
</script>

<template>
    <Head :title="`${user.full_name} · Admin`" />
    <ConfirmDialog />

    <!-- Ban dialog -->
    <Dialog v-model:visible="banDialog" :header="t('admin.users.ban_header')" modal :style="{ width: '32rem' }">
        <p class="text-sm text-gray-500 mb-3">{{ t('admin.users.ban_desc') }}</p>
        <Textarea v-model="banForm.reason" class="w-full" rows="3" :placeholder="t('admin.users.ban_reason')" />
        <template #footer>
            <Button :label="t('common.cancel')" severity="secondary" @click="banDialog = false" />
            <Button :label="t('admin.users.ban')" severity="danger" icon="pi pi-ban" :loading="banForm.processing" @click="submitBan" />
        </template>
    </Dialog>

    <!-- Test notification dialog -->
    <Dialog v-model:visible="notifyDialog" :header="t('admin.users.test_notification_header')" modal :style="{ width: '32rem' }">
        <InputText v-model="notifyForm.message" class="w-full" :placeholder="t('admin.users.test_notification_message')" />
        <template #footer>
            <Button :label="t('common.cancel')" severity="secondary" @click="notifyDialog = false" />
            <Button :label="t('admin.users.send')" icon="pi pi-send" :loading="notifyForm.processing" @click="submitNotify" />
        </template>
    </Dialog>

    <div class="flex flex-wrap items-center justify-between gap-2 mb-6">
        <Link href="/admin/users" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
            <i class="pi pi-arrow-left mr-1"></i> {{ t('admin.users.all_users') }}
        </Link>
        <div class="flex gap-2">
            <Link :href="`/admin/users/${user.id}/edit`">
                <Button :label="t('common.edit')" icon="pi pi-pencil" severity="secondary" size="small" />
            </Link>
            <Button :label="t('common.delete')" icon="pi pi-trash" severity="danger" size="small" @click="destroy" />
        </div>
    </div>

    <!-- Header card -->
    <div class="bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-2xl p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4 sm:gap-5">
            <img v-if="user.avatar_url" :src="user.avatar_url" :alt="user.full_name"
                 class="w-20 h-20 sm:w-24 sm:h-24 rounded-full object-cover ring-2 ring-gray-200 dark:ring-dark-700" />
            <div v-else class="w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-indigo-600 text-white flex items-center justify-center text-2xl sm:text-3xl font-semibold">
                {{ user.first_name[0] }}{{ user.last_name[0] }}
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl sm:text-2xl font-semibold truncate">{{ user.full_name }}</h1>
                <p class="text-sm text-gray-500 truncate">{{ user.email }}</p>
                <div class="flex flex-wrap items-center gap-2 mt-3">
                    <Tag v-for="r in user.roles" :key="r" :value="r" severity="info" />
                    <Tag v-if="user.email_verified_at" value="verified" severity="success" icon="pi pi-check" />
                    <Tag v-else value="unverified" severity="warn" icon="pi pi-exclamation-triangle" />
                    <Tag v-if="user.two_factor_enabled" value="2FA on" severity="success" icon="pi pi-shield" />
                    <Tag v-if="user.banned_at" :value="`banned ${formatDate(user.banned_at)}`" severity="danger" icon="pi pi-ban" />
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Actions -->
        <section class="lg:col-span-1 bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-2xl p-5 space-y-2">
            <h2 class="font-semibold mb-3">{{ t('admin.users.actions') }}</h2>

            <Button v-if="!user.email_verified_at" class="w-full justify-start"
                    :label="t('admin.users.mark_verified')" icon="pi pi-check" severity="success" size="small"
                    @click="action('verify')" />
            <Button v-else class="w-full justify-start"
                    :label="t('admin.users.clear_verification')" icon="pi pi-times" severity="secondary" size="small"
                    @click="confirmDestructive('Clear email verification?', 'unverify')" />

            <Button v-if="!user.email_verified_at" class="w-full justify-start"
                    :label="t('admin.users.resend_verification')" icon="pi pi-envelope" severity="secondary" size="small"
                    @click="action('resend-verification')" />

            <Button v-if="!user.banned_at && !isAdmin" class="w-full justify-start"
                    :label="t('admin.users.ban')" icon="pi pi-ban" severity="danger" size="small"
                    @click="confirmBan" />
            <Button v-else-if="user.banned_at" class="w-full justify-start"
                    :label="t('admin.users.unban')" icon="pi pi-refresh" severity="success" size="small"
                    @click="action('unban')" />

            <Button class="w-full justify-start" :label="t('admin.users.send_password_reset')" icon="pi pi-key"
                    severity="secondary" size="small"
                    @click="action('send-password-reset')" />

            <Button v-if="user.two_factor_enabled" class="w-full justify-start"
                    :label="t('admin.users.reset_2fa')" icon="pi pi-shield" severity="warn" size="small"
                    @click="confirmDestructive('Reset 2FA for this user? They will need to set it up again.', 'reset-2fa')" />

            <Button class="w-full justify-start" :label="t('admin.users.send_test')" icon="pi pi-bell"
                    severity="secondary" size="small"
                    @click="notifyDialog = true" />

            <Button v-if="!isAdmin" class="w-full justify-start"
                    :label="t('admin.users.impersonate')" icon="pi pi-user-edit" severity="warn" size="small"
                    @click="action('impersonate')" />
        </section>

        <!-- Meta -->
        <section class="lg:col-span-2 bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-2xl p-5">
            <h2 class="font-semibold mb-3">{{ t('admin.users.metadata') }}</h2>
            <dl class="grid grid-cols-2 gap-y-2 text-sm">
                <dt class="text-gray-500">{{ t('admin.users.user_id') }}</dt><dd class="font-mono">{{ user.id }}</dd>
                <dt class="text-gray-500">{{ t('admin.users.email') }}</dt><dd class="font-mono truncate">{{ user.email }}</dd>
                <dt class="text-gray-500">{{ t('admin.users.verified_at') }}</dt><dd class="font-mono">{{ formatDate(user.email_verified_at) }}</dd>
                <dt class="text-gray-500">{{ t('dashboard.member_since') }}</dt><dd class="font-mono">{{ formatDate(user.created_at) }}</dd>
                <dt class="text-gray-500">{{ t('admin.users.last_login') }}</dt><dd class="font-mono">{{ formatDate(user.last_login_at) }}</dd>
                <dt class="text-gray-500">{{ t('admin.users.two_factor') }}</dt><dd class="font-mono">{{ user.two_factor_enabled ? 'enabled' : 'off' }}</dd>
                <dt class="text-gray-500">{{ t('admin.users.banned_at') }}</dt><dd class="font-mono">{{ formatDate(user.banned_at) }}</dd>
                <dt v-if="user.banned_reason" class="text-gray-500">{{ t('admin.users.ban_reason_label') }}</dt>
                <dd v-if="user.banned_reason" class="font-mono">{{ user.banned_reason }}</dd>
                <dt class="text-gray-500">{{ t('admin.users.unread_notifications') }}</dt><dd class="font-mono">{{ user.unread_notifications_count }}</dd>
            </dl>
        </section>

        <!-- Customer memberships -->
        <section v-if="tenancy_enabled" class="lg:col-span-3 bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-2xl p-5">
            <h2 class="font-semibold mb-3">{{ t('admin.users.customer_memberships') }}</h2>
            <div v-if="user.customers.length" class="flex flex-wrap gap-2">
                <Link v-for="c in user.customers" :key="c.id" :href="`/admin/customers/${c.id}/edit`">
                    <Tag :value="c.name" severity="info" icon="pi pi-building" class="cursor-pointer hover:opacity-80" />
                </Link>
            </div>
            <p v-else class="text-sm text-gray-400 italic">{{ t('admin.users.not_member') }}</p>
        </section>

        <!-- Activity -->
        <section class="lg:col-span-3 bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold">{{ t('admin.users.recent_activity') }}</h2>
                <Link :href="`/admin/activity?user_id=${user.id}`" class="text-xs text-indigo-500 hover:underline">{{ t('admin.users.open_in_log') }}</Link>
            </div>
            <ul v-if="activity.length" class="divide-y divide-gray-100 dark:divide-dark-800">
                <li v-for="a in activity" :key="a.id" class="py-2 flex items-start gap-3 text-sm">
                    <Tag v-if="a.log_name" :value="a.log_name" severity="secondary" class="mt-0.5" />
                    <div class="flex-1">
                        <p>{{ a.description }}</p>
                        <p class="text-xs text-gray-500 font-mono">{{ formatDate(a.created_at) }} · {{ a.event ?? '—' }}</p>
                    </div>
                </li>
            </ul>
            <p v-else class="text-sm text-gray-400 italic">{{ t('admin.users.no_activity') }}</p>
        </section>
    </div>
</template>
