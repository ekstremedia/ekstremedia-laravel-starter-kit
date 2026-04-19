<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Password from 'primevue/password';
import ToggleSwitch from 'primevue/toggleswitch';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import type { PageProps } from '@/types';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface LocaleData {
    id: number;
    locale: string;
    subject: string;
    heading: string | null;
    body: string;
    action_text: string | null;
    action_url: string | null;
    has_compiled: boolean;
}

interface TemplateGroup {
    name: string;
    slug: string;
    variables: string[];
    locales: LocaleData[];
}

interface Props {
    settings: {
        mailer: string; host: string | null; port: number | null; encryption: string | null;
        username: string | null; has_password: boolean; from_address: string | null; from_name: string | null;
        enabled: boolean;
    };
    templates: TemplateGroup[];
}
const props = defineProps<Props>();

const page = usePage<PageProps>();
const userEmail = computed(() => page.props.auth?.user?.email ?? '');

// ── SMTP form ────────────────────────────────────────────────────
const smtpForm = useForm({
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

function saveSmtp() {
    smtpForm.patch('/admin/mail', { preserveScroll: true });
}
function sendTest() {
    router.post('/admin/mail/test', {}, { preserveScroll: true });
}

// ── Template editing ─────────────────────────────────────────────
const editingTemplate = ref<TemplateGroup | null>(null);
const editingLocale = ref<string>('en');
const previewDialogVisible = ref(false);
const previewHtml = ref('');
const loadingPreview = ref(false);
const savingTemplate = ref(false);
const sendingTest = ref(false);

const templateForm = useForm({
    subject: '',
    heading: '',
    body: '',
    action_text: '',
    action_url: '',
});

const localeOptions = [
    { label: '🇬🇧 English', value: 'en' },
    { label: '🇳🇴 Norwegian', value: 'no' },
];

function editTemplate(group: TemplateGroup) {
    editingTemplate.value = group;
    editingLocale.value = group.locales[0]?.locale ?? 'en';
    loadLocaleData(group, editingLocale.value);
}

function loadLocaleData(group: TemplateGroup, locale: string) {
    const data = group.locales.find(l => l.locale === locale);
    if (data) {
        templateForm.subject = data.subject;
        templateForm.heading = data.heading ?? '';
        templateForm.body = data.body;
        templateForm.action_text = data.action_text ?? '';
        templateForm.action_url = data.action_url ?? '';
    }
}

function switchLocale(locale: string) {
    editingLocale.value = locale;
    if (editingTemplate.value) {
        loadLocaleData(editingTemplate.value, locale);
    }
}

function currentLocaleData(): LocaleData | undefined {
    return editingTemplate.value?.locales.find(l => l.locale === editingLocale.value);
}

function saveTemplate() {
    const data = currentLocaleData();
    if (!data) return;

    savingTemplate.value = true;
    templateForm.patch(`/admin/mail/templates/${data.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            // Update local data
            if (data) {
                data.subject = templateForm.subject;
                data.heading = templateForm.heading;
                data.body = templateForm.body;
                data.action_text = templateForm.action_text;
                data.action_url = templateForm.action_url;
                data.has_compiled = true;
            }
        },
        onFinish: () => { savingTemplate.value = false; },
    });
}

function draftPayload() {
    return {
        subject: templateForm.subject,
        heading: templateForm.heading,
        body: templateForm.body,
        action_text: templateForm.action_text,
        action_url: templateForm.action_url,
    };
}

function previewTemplate() {
    const data = currentLocaleData();
    if (!data) return;

    loadingPreview.value = true;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    fetch(`/admin/mail/templates/${data.id}/preview`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(draftPayload()),
    })
        .then(r => r.json())
        .then(json => {
            previewHtml.value = json.html;
            previewDialogVisible.value = true;
        })
        .finally(() => { loadingPreview.value = false; });
}

function sendTemplateTest() {
    const data = currentLocaleData();
    if (!data) return;

    sendingTest.value = true;
    router.post(`/admin/mail/templates/${data.id}/test`, {
        email: userEmail.value,
        ...draftPayload(),
    }, {
        preserveScroll: true,
        onFinish: () => { sendingTest.value = false; },
    });
}

function closeEditor() {
    editingTemplate.value = null;
}
</script>

<template>
    <div>
    <Head title="Mail · Admin" />

    <!-- Preview dialog -->
    <Dialog v-model:visible="previewDialogVisible" :header="t('admin.mail.preview_title')" modal :style="{ width: '48rem' }" :dismissableMask="true">
        <div class="bg-gray-100 dark:bg-dark-900 rounded-lg p-2" style="min-height: 400px">
            <iframe :srcdoc="previewHtml" sandbox="" referrerpolicy="no-referrer" title="Email preview" class="w-full rounded" style="min-height: 500px; border: none;" />
        </div>
    </Dialog>

    <h1 class="text-2xl font-semibold mb-6">{{ t('admin.mail.title') }}</h1>

    <Tabs value="smtp">
        <TabList>
            <Tab value="smtp"><i class="pi pi-cog mr-2"></i>{{ t('admin.mail.smtp_tab') }}</Tab>
            <Tab value="templates"><i class="pi pi-file-edit mr-2"></i>{{ t('admin.mail.templates_tab') }}</Tab>
        </TabList>

        <TabPanels>
            <!-- ── Tab 1: SMTP ──────────────────────────────────────── -->
            <TabPanel value="smtp">
                <form @submit.prevent="saveSmtp" class="max-w-3xl bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl p-6 space-y-4 mt-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-1">{{ t('admin.mail.mailer') }}</label>
                            <InputText v-model="smtpForm.mailer" class="w-full" />
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="text-sm">{{ t('admin.mail.enabled') }}</label>
                            <ToggleSwitch v-model="smtpForm.enabled" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm mb-1">{{ t('admin.mail.host') }}</label>
                            <InputText v-model="smtpForm.host" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">{{ t('admin.mail.port') }}</label>
                            <InputNumber v-model="smtpForm.port" class="w-full" :useGrouping="false" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm mb-1">{{ t('admin.mail.encryption') }}</label>
                            <Select v-model="smtpForm.encryption" :options="encryptionOptions" optionLabel="label" optionValue="value" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">{{ t('admin.mail.username') }}</label>
                            <InputText v-model="smtpForm.username" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">{{ t('admin.mail.password') }}</label>
                            <Password v-model="smtpForm.password" toggleMask :feedback="false" class="w-full" inputClass="w-full" :placeholder="settings.has_password ? t('admin.mail.password_unchanged') : t('admin.mail.password_set')" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-1">{{ t('admin.mail.from_address') }}</label>
                            <InputText v-model="smtpForm.from_address" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">{{ t('admin.mail.from_name') }}</label>
                            <InputText v-model="smtpForm.from_name" class="w-full" />
                        </div>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <Button type="submit" :label="t('common.save')" icon="pi pi-check" :loading="smtpForm.processing" />
                        <Button type="button" :label="t('admin.mail.send_test')" icon="pi pi-send" severity="secondary" @click="sendTest" />
                    </div>
                </form>
            </TabPanel>

            <!-- ── Tab 2: Email Templates ───────────────────────────── -->
            <TabPanel value="templates">
                <div class="mt-4">
                    <!-- Template list -->
                    <div v-if="!editingTemplate" class="grid gap-3 max-w-4xl">
                        <button v-for="group in templates" :key="group.slug"
                                type="button"
                                class="w-full text-left bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl p-4 flex items-center justify-between cursor-pointer hover:border-indigo-300 dark:hover:border-indigo-700 transition-colors focus-visible:outline-2 focus-visible:outline-indigo-500"
                                @click="editTemplate(group)">
                            <div>
                                <h3 class="font-medium">{{ group.name }}</h3>
                                <p class="text-xs text-gray-500 mt-1">
                                    <code class="bg-gray-100 dark:bg-dark-800 px-1.5 py-0.5 rounded">{{ group.slug }}</code>
                                    <span class="ml-2">
                                        <Tag v-for="l in group.locales" :key="l.locale" :value="l.locale.toUpperCase()" severity="secondary" class="ml-1" />
                                    </span>
                                </p>
                            </div>
                            <i class="pi pi-chevron-right text-gray-400"></i>
                        </button>
                    </div>

                    <!-- Template editor -->
                    <div v-else class="max-w-4xl">
                        <div class="flex items-center gap-3 mb-4">
                            <Button icon="pi pi-arrow-left" severity="secondary" size="small" text @click="closeEditor" />
                            <h2 class="text-lg font-medium">{{ editingTemplate.name }}</h2>
                            <code class="text-xs bg-gray-100 dark:bg-dark-800 px-1.5 py-0.5 rounded">{{ editingTemplate.slug }}</code>
                        </div>

                        <!-- Locale switcher -->
                        <div class="flex gap-2 mb-4">
                            <Button v-for="opt in localeOptions" :key="opt.value"
                                    :label="opt.label"
                                    :severity="editingLocale === opt.value ? 'primary' : 'secondary'"
                                    size="small"
                                    @click="switchLocale(opt.value)" />
                        </div>

                        <div class="bg-white dark:bg-dark-900 border border-gray-200 dark:border-dark-800 rounded-xl p-6 space-y-4">
                            <div>
                                <label class="block text-sm mb-1">{{ t('admin.mail.subject') }}</label>
                                <InputText v-model="templateForm.subject" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm mb-1">{{ t('admin.mail.heading') }}</label>
                                <InputText v-model="templateForm.heading" class="w-full" :placeholder="t('admin.mail.heading_placeholder')" />
                            </div>
                            <div>
                                <label class="block text-sm mb-1">{{ t('admin.mail.body') }}</label>
                                <Textarea v-model="templateForm.body" class="w-full font-mono text-sm" rows="8" autoResize />
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm mb-1">{{ t('admin.mail.button_text') }}</label>
                                    <InputText v-model="templateForm.action_text" class="w-full" :placeholder="t('admin.mail.button_text_placeholder')" />
                                </div>
                                <div>
                                    <label class="block text-sm mb-1">{{ t('admin.mail.button_url') }}</label>
                                    <InputText v-model="templateForm.action_url" class="w-full" placeholder="https://..." />
                                </div>
                            </div>

                            <!-- Variables helper -->
                            <div class="border-t border-gray-100 dark:border-dark-800 pt-3">
                                <p class="text-xs text-gray-500 mb-2">{{ t('admin.mail.available_variables') }}</p>
                                <div class="flex flex-wrap gap-1">
                                    <Tag v-for="v in editingTemplate.variables" :key="v" :value="`{{ ${v} }}`" severity="secondary" class="font-mono text-xs" />
                                </div>
                            </div>

                            <div class="flex gap-2 pt-2">
                                <Button :label="t('common.save')" icon="pi pi-check" :loading="savingTemplate" @click="saveTemplate" />
                                <Button :label="t('admin.mail.preview')" icon="pi pi-eye" severity="secondary" :loading="loadingPreview" @click="previewTemplate" />
                                <Button :label="t('admin.mail.send_test_to_me')" icon="pi pi-send" severity="secondary" :loading="sendingTest" @click="sendTemplateTest" />
                            </div>
                        </div>
                    </div>
                </div>
            </TabPanel>
        </TabPanels>
    </Tabs>
    </div>
</template>
