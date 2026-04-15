<script setup lang="ts">
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, ref, onMounted } from 'vue';
import { gsap } from 'gsap';
import { useToast } from 'primevue/usetoast';
import AppLayout from '@/Layouts/AppLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import type { PageProps } from '@/types';

const { t } = useI18n();
const toast = useToast();
const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user!);

const sectionsRef = ref<HTMLElement>();

onMounted(() => {
    if (sectionsRef.value) {
        gsap.from(sectionsRef.value.children, {
            y: 20,
            opacity: 0,
            duration: 0.4,
            stagger: 0.1,
            ease: 'power2.out',
            delay: 0.1,
        });
    }
});

// --- Avatar ---
const avatarInput = ref<HTMLInputElement | null>(null);
const avatarUploading = ref(false);
const avatarPreview = computed(() => user.value.avatar_url);
const avatarInitials = computed(() => {
    const f = (user.value.first_name?.trim() ?? '')[0] ?? '';
    const l = (user.value.last_name?.trim() ?? '')[0] ?? '';
    return (f + l).toUpperCase();
});

function pickAvatar() {
    avatarInput.value?.click();
}

function onAvatarChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    const form = new FormData();
    form.append('avatar', file);
    avatarUploading.value = true;
    router.post('/profile/avatar', form, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => toast.add({ severity: 'success', detail: 'Photo updated', life: 3000 }),
        onError: (errors) => {
            const first = Object.values(errors)[0] as string;
            toast.add({ severity: 'error', detail: first ?? 'Upload failed', life: 4000 });
        },
        onFinish: () => {
            avatarUploading.value = false;
            if (avatarInput.value) avatarInput.value.value = '';
        },
    });
}

function removeAvatar() {
    router.delete('/profile/avatar', {
        preserveScroll: true,
        onSuccess: () => toast.add({ severity: 'success', detail: 'Photo removed', life: 3000 }),
    });
}

// --- Profile Info ---
const profileForm = useForm({
    first_name: user.value.first_name,
    last_name: user.value.last_name,
    email: user.value.email,
});

function saveProfile() {
    profileForm.put('/user/profile-information', {
        preserveScroll: true,
        onSuccess: () => toast.add({ severity: 'success', detail: t('profile.saved'), life: 3000 }),
    });
}

// --- Password ---
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function updatePassword() {
    passwordForm.put('/user/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            toast.add({ severity: 'success', detail: t('profile.saved'), life: 3000 });
        },
    });
}

// --- Two-Factor Auth ---
const twoFactorEnabled = computed(() => !!user.value.two_factor_enabled);
const enabling = ref(false);
const confirming = ref(false);
const qrCode = ref('');
const recoveryCodes = ref<string[]>([]);
const confirmCode = ref('');
const confirmError = ref('');
const disabling = ref(false);
const showRecovery = ref(false);

// Password confirmation state
const showPasswordConfirm = ref(false);
const confirmPasswordInput = ref('');
const confirmPasswordError = ref('');
let pendingAction: (() => Promise<void>) | null = null;

async function confirmPassword(password: string): Promise<boolean> {
    try {
        const res = await window.fetch('/user/confirm-password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content
                    ?? document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: JSON.stringify({ password }),
        });
        return res.ok;
    } catch {
        return false;
    }
}

async function requirePasswordConfirm(action: () => Promise<void>) {
    pendingAction = action;
    confirmPasswordInput.value = '';
    confirmPasswordError.value = '';
    showPasswordConfirm.value = true;
}

async function submitPasswordConfirm() {
    confirmPasswordError.value = '';
    const ok = await confirmPassword(confirmPasswordInput.value);
    if (!ok) {
        confirmPasswordError.value = t('profile.confirm_password_error');
        return;
    }
    showPasswordConfirm.value = false;
    confirmPasswordInput.value = '';
    if (pendingAction) await pendingAction();
    pendingAction = null;
}

function cancelPasswordConfirm() {
    showPasswordConfirm.value = false;
    confirmPasswordInput.value = '';
    confirmPasswordError.value = '';
    pendingAction = null;
}

async function enableTwoFactor() {
    await requirePasswordConfirm(async () => {
        enabling.value = true;
        try {
            await fetch('/user/two-factor-authentication', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });
            const qrRes = await fetch('/user/two-factor-qr-code', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            const qrData = await qrRes.json();
            qrCode.value = qrData.svg;
            confirming.value = true;
        } catch {
            toast.add({ severity: 'error', detail: t('profile.failed_enable_2fa'), life: 4000 });
        } finally {
            enabling.value = false;
        }
    });
}

async function confirmTwoFactor() {
    confirmError.value = '';
    try {
        const res = await fetch('/user/confirmed-two-factor-authentication', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getToken(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: JSON.stringify({ code: confirmCode.value }),
        });
        if (!res.ok) {
            confirmError.value = t('profile.invalid_2fa_code');
            return;
        }
        confirming.value = false;
        confirmCode.value = '';
        await fetchRecoveryCodes();
        showRecovery.value = true;
        router.reload({ only: ['auth'] });
    } catch {
        confirmError.value = t('profile.something_went_wrong');
    }
}

async function fetchRecoveryCodes() {
    const res = await fetch('/user/two-factor-recovery-codes', {
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });
    recoveryCodes.value = await res.json();
}

async function regenerateCodes() {
    await requirePasswordConfirm(async () => {
        await fetch('/user/two-factor-recovery-codes', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getToken(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        });
        await fetchRecoveryCodes();
        toast.add({ severity: 'success', detail: t('profile.recovery_codes_regenerated'), life: 3000 });
    });
}

async function disableTwoFactor() {
    await requirePasswordConfirm(async () => {
        disabling.value = true;
        try {
            await fetch('/user/two-factor-authentication', {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': getToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });
            showRecovery.value = false;
            recoveryCodes.value = [];
            router.reload({ only: ['auth'] });
            toast.add({ severity: 'info', detail: t('profile.two_factor_disabled_toast'), life: 3000 });
        } catch {
            toast.add({ severity: 'error', detail: t('profile.failed_disable_2fa'), life: 4000 });
        } finally {
            disabling.value = false;
        }
    });
}

async function showExistingRecoveryCodes() {
    await fetchRecoveryCodes();
    showRecovery.value = !showRecovery.value;
}

function getToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Head :title="t('profile.title')" />

    <AppLayout>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-8">
                {{ t('profile.title') }}
            </h1>

            <div ref="sectionsRef" class="space-y-6">
                <!-- Profile Photo -->
                <div class="p-6 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Profile photo</h2>
                    <p class="text-sm text-gray-500 dark:text-dark-400 mt-1 mb-5">
                        JPG, PNG, WebP or GIF. Up to 50&nbsp;MB. A square image works best.
                    </p>

                    <div class="flex items-center gap-6">
                        <div class="relative">
                            <img
                                v-if="avatarPreview"
                                :src="avatarPreview"
                                :alt="user.full_name"
                                class="w-24 h-24 rounded-full object-cover ring-2 ring-gray-200 dark:ring-dark-700"
                            />
                            <div
                                v-else
                                class="w-24 h-24 rounded-full bg-indigo-600 text-white flex items-center justify-center text-2xl font-semibold ring-2 ring-gray-200 dark:ring-dark-700"
                            >
                                {{ avatarInitials }}
                            </div>
                            <div
                                v-if="avatarUploading"
                                class="absolute inset-0 rounded-full bg-black/40 flex items-center justify-center"
                            >
                                <i class="pi pi-spin pi-spinner text-white text-xl"></i>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <input
                                ref="avatarInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                                class="hidden"
                                @change="onAvatarChange"
                            />
                            <button
                                type="button"
                                :disabled="avatarUploading"
                                @click="pickAvatar"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50 cursor-pointer"
                            >
                                <i class="pi pi-upload mr-2 text-xs"></i>{{ avatarPreview ? 'Replace photo' : 'Upload photo' }}
                            </button>
                            <button
                                v-if="avatarPreview"
                                type="button"
                                :disabled="avatarUploading"
                                @click="removeAvatar"
                                class="px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors cursor-pointer"
                            >
                                <i class="pi pi-trash mr-2 text-xs"></i>Remove
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Profile Information -->
                <div class="p-6 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ t('profile.info_title') }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-dark-400 mt-1 mb-5">
                        {{ t('profile.info_desc') }}
                    </p>

                    <form @submit.prevent="saveProfile" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <TextInput
                                v-model="profileForm.first_name"
                                :label="t('auth.first_name')"
                                :error="profileForm.errors.first_name"
                            />
                            <TextInput
                                v-model="profileForm.last_name"
                                :label="t('auth.last_name')"
                                :error="profileForm.errors.last_name"
                            />
                        </div>
                        <TextInput
                            v-model="profileForm.email"
                            type="email"
                            :label="t('auth.email')"
                            :error="profileForm.errors.email"
                        />
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="profileForm.processing"
                                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50 cursor-pointer"
                            >
                                {{ t('profile.save') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Password -->
                <div class="p-6 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ t('profile.password_title') }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-dark-400 mt-1 mb-5">
                        {{ t('profile.password_desc') }}
                    </p>

                    <form @submit.prevent="updatePassword" class="space-y-4">
                        <TextInput
                            v-model="passwordForm.current_password"
                            type="password"
                            :label="t('profile.current_password')"
                            :error="passwordForm.errors.current_password"
                        />
                        <TextInput
                            v-model="passwordForm.password"
                            type="password"
                            :label="t('profile.new_password')"
                            :error="passwordForm.errors.password"
                        />
                        <TextInput
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            :label="t('profile.confirm_password')"
                            :error="passwordForm.errors.password_confirmation"
                        />
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="passwordForm.processing"
                                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50 cursor-pointer"
                            >
                                {{ t('profile.update_password') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Two-Factor Authentication -->
                <div class="p-6 rounded-xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ t('profile.two_factor_title') }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-dark-400 mt-1 mb-5">
                        {{ t('profile.two_factor_desc') }}
                    </p>

                    <!-- Status -->
                    <p class="text-sm font-medium mb-4" :class="twoFactorEnabled ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-dark-400'">
                        {{ twoFactorEnabled ? t('profile.two_factor_enabled') : t('profile.two_factor_not_enabled') }}
                    </p>

                    <!-- Inline password confirmation -->
                    <div v-if="showPasswordConfirm" class="mb-4 p-4 rounded-lg bg-gray-50 dark:bg-dark-800 border border-gray-200 dark:border-dark-700 space-y-3">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ t('auth.confirm_password_title') }}</p>
                        <p class="text-sm text-gray-500 dark:text-dark-400">{{ t('auth.confirm_password_subtitle') }}</p>
                        <TextInput
                            v-model="confirmPasswordInput"
                            type="password"
                            :label="t('auth.password')"
                            :error="confirmPasswordError"
                            autofocus
                        />
                        <div class="flex gap-3">
                            <button
                                @click="submitPasswordConfirm"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors cursor-pointer"
                            >
                                {{ t('auth.confirm_password_submit') }}
                            </button>
                            <button
                                @click="cancelPasswordConfirm"
                                class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-dark-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-dark-800 transition-colors cursor-pointer"
                            >
                                {{ t('common.cancel') }}
                            </button>
                        </div>
                    </div>

                    <!-- Enable flow -->
                    <div v-if="!twoFactorEnabled && !confirming && !showPasswordConfirm">
                        <button
                            @click="enableTwoFactor"
                            :disabled="enabling"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50 cursor-pointer"
                        >
                            {{ t('profile.two_factor_enable') }}
                        </button>
                    </div>

                    <!-- QR Code + Confirm -->
                    <div v-if="confirming" class="space-y-4">
                        <p class="text-sm text-gray-600 dark:text-dark-300">
                            {{ t('profile.two_factor_qr_instructions') }}
                        </p>
                        <div class="flex justify-center p-4 bg-white rounded-lg" v-html="qrCode"></div>
                        <TextInput
                            v-model="confirmCode"
                            :label="t('profile.two_factor_enter_code')"
                            :error="confirmError"
                            autocomplete="one-time-code"
                            inputmode="numeric"
                        />
                        <button
                            @click="confirmTwoFactor"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors cursor-pointer"
                        >
                            {{ t('profile.two_factor_confirm') }}
                        </button>
                    </div>

                    <!-- Enabled: show recovery codes + disable -->
                    <div v-if="twoFactorEnabled && !confirming" class="space-y-4">
                        <!-- Recovery codes -->
                        <div v-if="showRecovery && recoveryCodes.length" class="space-y-3">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ t('profile.two_factor_recovery_title') }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-dark-400">
                                {{ t('profile.two_factor_recovery_desc') }}
                            </p>
                            <div class="grid grid-cols-2 gap-2 p-4 rounded-lg bg-gray-50 dark:bg-dark-800 font-mono text-sm text-gray-700 dark:text-dark-300">
                                <span v-for="code in recoveryCodes" :key="code">{{ code }}</span>
                            </div>
                            <button
                                @click="regenerateCodes"
                                class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer"
                            >
                                {{ t('profile.two_factor_regenerate') }}
                            </button>
                        </div>

                        <div class="flex gap-3">
                            <button
                                @click="showExistingRecoveryCodes"
                                class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-dark-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-dark-800 transition-colors cursor-pointer"
                            >
                                {{ showRecovery ? t('profile.two_factor_recovery_title') : t('profile.two_factor_recovery_title') }}
                            </button>
                            <button
                                @click="disableTwoFactor"
                                :disabled="disabling"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-red-600 hover:bg-red-700 text-white transition-colors disabled:opacity-50 cursor-pointer"
                            >
                                {{ t('profile.two_factor_disable') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
