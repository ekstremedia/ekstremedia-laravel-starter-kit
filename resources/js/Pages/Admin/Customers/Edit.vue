<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useConfirm } from 'primevue/useconfirm';
import CommandLayout from '@/Layouts/CommandLayout.vue';
import Field from '@/Components/Command/Field.vue';
import Toggle from '@/Components/Command/Toggle.vue';
import Icon from '@/Components/Command/Icon.vue';
import Dot from '@/Components/Command/Dot.vue';
import { useCommandToasts } from '@/composables/useCommandToasts';

defineOptions({ layout: CommandLayout });

const { t } = useI18n();
const { push } = useCommandToasts();
const confirmer = useConfirm();

interface Member { id: number; email: string; full_name: string }
interface CustomerData {
    id: number;
    slug: string;
    name: string;
    status: 'active' | 'suspended';
    files_feature_enabled: boolean;
    users: Member[];
}

const props = defineProps<{ customer: CustomerData; global_files_feature_enabled: boolean }>();

const form = useForm({
    name: props.customer.name,
    status: props.customer.status,
    // Coerce the per-customer flag to false whenever the global feature is
    // off so a stale `true` can't be submitted while the toggle is disabled.
    files_feature_enabled: props.global_files_feature_enabled && props.customer.files_feature_enabled,
});

const statusOpen = ref(false);

function save() {
    form.put(`/admin/customers/${props.customer.id}`, {
        preserveScroll: true,
        onSuccess: () => push(t('admin.customers.toast_updated'), 'success'),
    });
}

const memberForm = useForm({ email: '' });

function attach() {
    memberForm.post(`/admin/customers/${props.customer.id}/members`, {
        preserveScroll: true,
        onSuccess: () => {
            memberForm.reset('email');
            push(t('admin.customers.toast_member_added'), 'success');
        },
    });
}

function detach(member: Member) {
    confirmer.require({
        group: 'command',
        message: t('admin.customers.confirm_detach', { email: member.email, name: props.customer.name }),
        header: t('admin.customers.detach'),
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        acceptLabel: t('admin.customers.detach'),
        rejectLabel: t('common.cancel'),
        accept: () => {
            router.delete(`/admin/customers/${props.customer.id}/members/${member.id}`, {
                preserveScroll: true,
                onSuccess: () => push(t('admin.customers.toast_member_removed', { email: member.email }), 'danger'),
            });
        },
    });
}
</script>

<template>
    <div>
        <Head :title="`${customer.name} · Admin`" />

        <div :style="{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', marginBottom: '18px', gap: '16px' }">
        <div :style="{ minWidth: 0 }">
            <h1 :style="{ margin: 0, fontSize: '20px', fontWeight: 600, letterSpacing: '-0.01em', color: 'var(--fg)' }">
                {{ customer.name }}
            </h1>
            <div
                class="cmd-mono"
                :style="{ marginTop: '4px', fontSize: '11.5px', color: 'var(--fg-mute)', display: 'flex', alignItems: 'center', gap: '8px' }"
            >
                <code :style="{ background: 'var(--panel2)', border: '1px solid var(--border)', padding: '1px 6px', borderRadius: '3px', color: 'var(--fg-dim)' }">/c/{{ customer.slug }}</code>
                <span>·</span>
                <span :style="{ display: 'inline-flex', alignItems: 'center', gap: '5px' }">
                    <Dot :color="customer.status === 'active' ? 'var(--success)' : 'var(--warning)'" :size="5" />
                    <span :style="{ color: customer.status === 'active' ? 'var(--fg)' : 'var(--fg-dim)' }">{{ customer.status }}</span>
                </span>
            </div>
        </div>
        <Link
            href="/admin/customers"
            :style="{ fontSize: '11.5px', color: 'var(--fg-dim)', textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: '5px' }"
        >
            <Icon name="chevR" :size="10" :style="{ transform: 'rotate(180deg)' }" />
            {{ t('common.back') }}
        </Link>
    </div>

    <div :style="{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '16px' }">
        <!-- Settings -->
        <section class="cmd-card" :style="{ padding: '20px' }">
            <h2 :style="{ fontSize: '14px', fontWeight: 600, color: 'var(--fg)', margin: '0 0 16px' }">
                {{ t('admin.customers.settings') }}
            </h2>
            <form @submit.prevent="save" :style="{ display: 'flex', flexDirection: 'column', gap: '14px' }">
                <Field
                    v-model="form.name"
                    :label="t('common.name')"
                    :error="form.errors.name"
                />

                <div>
                    <div
                        class="cmd-mono cmd-uc"
                        :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '6px', fontWeight: 500, letterSpacing: '0.06em' }"
                    >{{ t('common.status') }}</div>
                    <div :style="{ position: 'relative' }">
                        <button
                            type="button"
                            @click="statusOpen = !statusOpen"
                            :style="{
                                width: '100%',
                                background: 'var(--panel2)',
                                border: '1px solid var(--border)',
                                borderRadius: '5px',
                                padding: '8px 10px',
                                color: 'var(--fg)',
                                fontSize: '13px',
                                cursor: 'pointer',
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                fontFamily: 'inherit',
                            }"
                        >
                            <span :style="{ textTransform: 'capitalize' }">{{ form.status }}</span>
                            <Icon name="chevD" :size="11" />
                        </button>
                        <div
                            v-if="statusOpen"
                            :style="{
                                position: 'absolute',
                                top: '100%',
                                left: 0,
                                right: 0,
                                marginTop: '2px',
                                zIndex: 10,
                                background: 'var(--panel)',
                                border: '1px solid var(--border)',
                                borderRadius: '5px',
                                overflow: 'hidden',
                                boxShadow: '0 8px 24px rgba(0,0,0,0.35)',
                            }"
                        >
                            <div
                                v-for="opt in (['active', 'suspended'] as const)"
                                :key="opt"
                                @click="form.status = opt; statusOpen = false"
                                :style="{
                                    padding: '7px 10px',
                                    fontSize: '12px',
                                    cursor: 'pointer',
                                    background: opt === form.status ? 'var(--accent-soft)' : 'transparent',
                                    color: 'var(--fg)',
                                    textTransform: 'capitalize',
                                }"
                            >{{ opt }}</div>
                        </div>
                    </div>
                    <p :style="{ fontSize: '11px', color: 'var(--fg-mute)', marginTop: '5px' }">{{ t('admin.customers.suspended_hint') }}</p>
                </div>

                <div :style="{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '16px', paddingTop: '6px' }">
                    <div :style="{ flex: 1, minWidth: 0 }">
                        <div :style="{ fontSize: '12.5px', fontWeight: 500, color: 'var(--fg)', display: 'inline-flex', alignItems: 'center', gap: '6px' }">
                            <Icon name="disk" :size="12" :style="{ color: 'var(--accent)' }" />
                            {{ t('admin.customers.files_enabled') }}
                        </div>
                        <div :style="{ fontSize: '11px', color: 'var(--fg-dim)', marginTop: '2px' }">{{ t('admin.customers.files_enabled_hint') }}</div>
                        <i18n-t
                            v-if="!global_files_feature_enabled"
                            keypath="admin.customers.files_global_disabled_hint"
                            tag="p"
                            :style="{ fontSize: '11px', color: 'var(--warning)', marginTop: '5px' }"
                        >
                            <template #appSettings>
                                <Link
                                    href="/admin/settings"
                                    :style="{ color: 'var(--warning)', textDecoration: 'underline' }"
                                >{{ t('admin.customers.app_settings_link') }}</Link>
                            </template>
                        </i18n-t>
                    </div>
                    <Toggle
                        v-model="form.files_feature_enabled"
                        :disabled="!global_files_feature_enabled"
                        :label="t('admin.customers.files_feature')"
                    />
                </div>
                <p v-if="form.errors.files_feature_enabled" :style="{ fontSize: '11px', color: 'var(--danger)', marginTop: '-6px' }">
                    {{ form.errors.files_feature_enabled }}
                </p>

                <div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        :style="{
                            background: 'var(--accent)',
                            color: '#fff',
                            border: 'none',
                            padding: '7px 12px',
                            borderRadius: '5px',
                            fontSize: '12px',
                            fontWeight: 500,
                            cursor: form.processing ? 'not-allowed' : 'pointer',
                            opacity: form.processing ? 0.6 : 1,
                            fontFamily: 'inherit',
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: '6px',
                        }"
                    >
                        <Icon name="arrow" :size="12" />
                        {{ t('common.save') }}
                    </button>
                </div>
            </form>
        </section>

        <!-- Members -->
        <section class="cmd-card" :style="{ padding: '20px' }">
            <h2 :style="{ fontSize: '14px', fontWeight: 600, color: 'var(--fg)', margin: '0 0 16px' }">
                {{ t('admin.customers.member_count', { count: customer.users.length }) }}
            </h2>

            <form @submit.prevent="attach" :style="{ display: 'flex', gap: '6px', marginBottom: '14px' }">
                <input
                    v-model="memberForm.email"
                    type="email"
                    :placeholder="t('admin.customers.add_member_placeholder')"
                    :style="{
                        flex: 1,
                        background: 'var(--panel2)',
                        border: '1px solid var(--border)',
                        borderRadius: '5px',
                        padding: '7px 10px',
                        color: 'var(--fg)',
                        fontSize: '12.5px',
                        outline: 'none',
                        fontFamily: 'inherit',
                    }"
                />
                <button
                    type="submit"
                    :disabled="memberForm.processing"
                    :style="{
                        background: 'var(--accent)',
                        color: '#fff',
                        border: 'none',
                        padding: '7px 11px',
                        borderRadius: '5px',
                        fontSize: '11.5px',
                        fontWeight: 500,
                        cursor: memberForm.processing ? 'not-allowed' : 'pointer',
                        opacity: memberForm.processing ? 0.6 : 1,
                        fontFamily: 'inherit',
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '5px',
                    }"
                >
                    <Icon name="plus" :size="12" />
                    {{ t('common.add') }}
                </button>
            </form>
            <p v-if="memberForm.errors.email" :style="{ fontSize: '11px', color: 'var(--danger)', marginTop: '-10px', marginBottom: '10px' }">
                {{ memberForm.errors.email }}
            </p>

            <ul
                v-if="customer.users.length"
                :style="{ listStyle: 'none', padding: 0, margin: 0 }"
            >
                <li
                    v-for="member in customer.users"
                    :key="member.id"
                    :style="{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        padding: '10px 0',
                        borderBottom: '1px solid var(--border)',
                    }"
                >
                    <div :style="{ minWidth: 0 }">
                        <div :style="{ fontSize: '12.5px', fontWeight: 500, color: 'var(--fg)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }">
                            {{ member.full_name }}
                        </div>
                        <div class="cmd-mono" :style="{ fontSize: '11px', color: 'var(--fg-dim)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }">
                            {{ member.email }}
                        </div>
                    </div>
                    <button
                        type="button"
                        :title="'Fjern'"
                        @click="detach(member)"
                        :style="{ background: 'transparent', border: 'none', color: 'var(--fg-mute)', cursor: 'pointer', padding: '6px', borderRadius: '3px', display: 'flex', alignItems: 'center', justifyContent: 'center' }"
                        class="cmd-member-remove"
                    >
                        <Icon name="trash" :size="12" />
                    </button>
                </li>
            </ul>
            <p v-else :style="{ fontSize: '12px', color: 'var(--fg-mute)', padding: '20px 0', textAlign: 'center' }">
                {{ t('admin.customers.no_members') }}
            </p>
        </section>
    </div>
    </div>
</template>

<style scoped>
.cmd-member-remove:hover { color: var(--danger) !important; }
</style>
