<script setup lang="ts">
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthCard from '@/Components/Command/AuthCard.vue';
import Field from '@/Components/Command/Field.vue';
import Icon from '@/Components/Command/Icon.vue';
import type { PageProps } from '@/types';

defineOptions({ layout: AuthLayout });

const { t } = useI18n();
const page = usePage<PageProps>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const easyLoginForm = useForm({});

const oauthProviders = computed(() => page.props.oauth?.providers ?? []);
const registrationOpen = computed(() => page.props.app_settings?.registration_open !== false);

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}

function easyLogin() {
    easyLoginForm.post('/login/dev');
}
</script>

<template>
    <div>
        <Head :title="t('nav.login')" />

        <AuthCard
            :eyebrow="t('auth.login_title')"
            :title="t('auth.login_subtitle')"
        >
        <form @submit.prevent="submit" :style="{ display: 'flex', flexDirection: 'column', gap: '14px' }">
            <Field
                v-model="form.email"
                type="email"
                :label="t('auth.email')"
                :placeholder="t('auth.email')"
                :error="form.errors.email"
                autocomplete="email"
                autofocus
            />
            <Field
                v-model="form.password"
                type="password"
                :label="t('auth.password')"
                :placeholder="t('auth.password')"
                :error="form.errors.password"
                autocomplete="current-password"
            />

            <div :style="{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', fontSize: '12px' }">
                <label :style="{ display: 'inline-flex', alignItems: 'center', gap: '8px', cursor: 'pointer', color: 'var(--fg-dim)' }">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        :style="{ accentColor: 'var(--accent)' }"
                    />
                    {{ t('auth.remember_me') }}
                </label>
                <Link
                    href="/forgot-password"
                    :style="{ color: 'var(--accent)', textDecoration: 'none' }"
                >{{ t('auth.forgot_password') }}</Link>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                :style="{
                    background: 'var(--accent)',
                    color: '#fff',
                    border: 'none',
                    padding: '10px 14px',
                    borderRadius: '5px',
                    fontSize: '12.5px',
                    fontWeight: 500,
                    cursor: form.processing ? 'not-allowed' : 'pointer',
                    opacity: form.processing ? 0.6 : 1,
                    fontFamily: 'inherit',
                    display: 'inline-flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '6px',
                    marginTop: '4px',
                }"
            >
                {{ t('nav.login') }}
                <Icon name="arrow" :size="12" />
            </button>

            <button
                v-if="page.props.debug.easy_login_enabled"
                type="button"
                :disabled="easyLoginForm.processing"
                @click="easyLogin"
                :style="{
                    background: 'transparent',
                    color: 'var(--accent)',
                    border: '1px dashed var(--accent-border)',
                    padding: '9px 14px',
                    borderRadius: '5px',
                    fontSize: '12.5px',
                    cursor: easyLoginForm.processing ? 'not-allowed' : 'pointer',
                    opacity: easyLoginForm.processing ? 0.6 : 1,
                    fontFamily: 'inherit',
                }"
            >{{ t('auth.easy_login') }}</button>
        </form>

        <!-- OAuth providers -->
        <div v-if="oauthProviders.length > 0" :style="{ marginTop: '20px' }">
            <div
                :style="{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '10px',
                    margin: '0 0 12px',
                }"
            >
                <div :style="{ flex: 1, height: '1px', background: 'var(--border)' }" />
                <span
                    class="cmd-mono cmd-uc"
                    :style="{ fontSize: '9.5px', color: 'var(--fg-mute)', letterSpacing: '0.08em' }"
                >{{ t('auth.or_continue_with') }}</span>
                <div :style="{ flex: 1, height: '1px', background: 'var(--border)' }" />
            </div>
            <div :style="{ display: 'grid', gap: '8px', gridTemplateColumns: oauthProviders.length > 1 ? '1fr 1fr' : '1fr' }">
                <a
                    v-for="p in oauthProviders"
                    :key="p.name"
                    :href="`/auth/${p.name}/redirect`"
                    :style="{
                        display: 'inline-flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: '8px',
                        padding: '8px 12px',
                        background: 'var(--panel2)',
                        border: '1px solid var(--border)',
                        borderRadius: '5px',
                        color: 'var(--fg)',
                        fontSize: '12px',
                        textDecoration: 'none',
                    }"
                >
                    <i :class="['pi', `pi-${p.name}`]" :style="{ fontSize: '14px' }"></i>
                    <span>{{ p.label }}</span>
                </a>
            </div>
        </div>

        <!-- Register link -->
        <p
            v-if="registrationOpen"
            :style="{ marginTop: '20px', textAlign: 'center', fontSize: '12px', color: 'var(--fg-dim)' }"
        >
            {{ t('auth.no_account') }}
            <Link href="/register" :style="{ color: 'var(--accent)', textDecoration: 'none', fontWeight: 500 }">
                {{ t('nav.register') }}
            </Link>
        </p>
    </AuthCard>
    </div>
</template>
