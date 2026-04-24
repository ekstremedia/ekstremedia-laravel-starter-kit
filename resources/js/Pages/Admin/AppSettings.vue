<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CommandLayout from '@/Layouts/CommandLayout.vue';
import Dot from '@/Components/Command/Dot.vue';
import Icon from '@/Components/Command/Icon.vue';
import Skeleton from '@/Components/Command/Skeleton.vue';
import Toggle from '@/Components/Command/Toggle.vue';
import { useCommandToasts } from '@/composables/useCommandToasts';

defineOptions({ layout: CommandLayout });

type Severity = 'info' | 'warn' | 'danger' | 'success';

interface Settings {
    site_up: boolean;
    registration_open: boolean;
    login_enabled: boolean;
    require_email_verification: boolean;
    default_role: string;
    require_2fa_for_admins: boolean;
    send_welcome_notification: boolean;
    maintenance_message: string | null;
    announcement_banner: string | null;
    announcement_severity: Severity;
    files_feature_enabled: boolean;
    max_share_days: number;
    // null/null = unlimited, -1 = explicit unlimited, 0 = blocked, N>0 = cap.
    default_personal_storage_bytes: number | null;
}

interface Props {
    settings: Settings;
    roles: string[];
}

const props = defineProps<Props>();
const { push } = useCommandToasts();

const form = useForm({
    site_up: props.settings.site_up,
    registration_open: props.settings.registration_open,
    login_enabled: props.settings.login_enabled,
    require_email_verification: props.settings.require_email_verification,
    default_role: props.settings.default_role,
    require_2fa_for_admins: props.settings.require_2fa_for_admins,
    send_welcome_notification: props.settings.send_welcome_notification,
    maintenance_message: props.settings.maintenance_message ?? '',
    announcement_banner: props.settings.announcement_banner ?? '',
    announcement_severity: props.settings.announcement_severity as Severity,
    files_feature_enabled: props.settings.files_feature_enabled,
    max_share_days: props.settings.max_share_days,
    default_personal_storage_bytes: props.settings.default_personal_storage_bytes,
});

// `v-model.number` gives us '' when the user clears the field, but the
// backend wants a proper null (not 0, which means "blocked"). Round-trip
// through a computed that normalises empty/NaN back to null.
const defaultPersonalStorageBytes = computed<number | null>({
    get: () => form.default_personal_storage_bytes,
    set: (v) => {
        form.default_personal_storage_bytes = v === null || Number.isNaN(v as unknown as number) || (v as unknown as string) === '' ? null : Number(v);
    },
});

const dirty = computed(() => form.isDirty);
const loading = ref(true);
setTimeout(() => { loading.value = false; }, 700);

type SectionId = 'access' | 'policy' | 'banner' | 'fs';
const active = ref<SectionId>('access');
const sections: { id: SectionId; label: string }[] = [
    { id: 'access', label: 'Tilgang' },
    { id: 'policy', label: 'Retningslinjer' },
    { id: 'banner', label: 'Annonsering' },
    { id: 'fs', label: 'Filsystem' },
];

const severityOptions: { id: Severity; label: string }[] = [
    { id: 'info', label: 'Info' },
    { id: 'warn', label: 'Advarsel' },
    { id: 'danger', label: 'Feil' },
    { id: 'success', label: 'Ok' },
];

function save() {
    form.patch('/admin/settings', {
        preserveScroll: true,
        onSuccess: () => {
            push('Innstillinger lagret', 'success');
            form.defaults();
        },
        onError: () => push('Kunne ikke lagre — se felter', 'danger'),
    });
}

function discard() {
    form.reset();
    push('Endringer forkastet', 'info');
}

const roleOpen = ref(false);
</script>

<template>
    <Head title="Appinnstillinger" />

    <div :style="{ display: 'flex', gap: '16px', minHeight: 'calc(100vh - 42px - 48px)' }">
        <!-- Section sidebar -->
        <aside
            :style="{
                width: 'var(--settings-aside-w)',
                padding: '14px 8px',
                background: 'var(--bg2)',
                flexShrink: 0,
                borderRadius: 'var(--radius-card)',
                border: '1px solid var(--border)',
                alignSelf: 'flex-start',
            }"
        >
            <div
                class="cmd-mono cmd-uc"
                :style="{ fontSize: '9px', color: 'var(--fg-mute)', padding: '0 8px 8px', fontWeight: 500 }"
            >Seksjoner</div>
            <div
                v-for="s in sections"
                :key="s.id"
                @click="active = s.id"
                :style="{
                    padding: '6px 10px',
                    fontSize: '12px',
                    borderRadius: '4px',
                    cursor: 'pointer',
                    marginBottom: '1px',
                    background: active === s.id ? 'var(--accent-soft)' : 'transparent',
                    color: active === s.id ? 'var(--fg)' : 'var(--fg-dim)',
                }"
            >{{ s.label }}</div>
        </aside>

        <!-- Content -->
        <div :style="{ flex: 1, minWidth: 0 }">
            <div :style="{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', marginBottom: '20px' }">
                <div>
                    <h1 :style="{ margin: 0, fontSize: '20px', fontWeight: 600, letterSpacing: '-0.01em', color: 'var(--fg)' }">Appinnstillinger</h1>
                    <div
                        class="cmd-mono"
                        :style="{ marginTop: '3px', fontSize: '11.5px', color: 'var(--fg-mute)' }"
                    >Globale brytere som påvirker alle ikke-admin-brukere</div>
                </div>
                <div :style="{ display: 'flex', gap: '6px', alignItems: 'center' }">
                    <span
                        v-if="dirty"
                        :style="{ fontSize: '11px', color: 'var(--warning)', display: 'flex', alignItems: 'center', gap: '5px' }"
                    >
                        <Dot color="var(--warning)" :size="5" />
                        Ulagrede endringer
                    </span>
                    <button
                        v-if="dirty"
                        type="button"
                        @click="discard"
                        :style="{ background: 'transparent', color: 'var(--fg-dim)', border: '1px solid var(--border)', padding: '5px 10px', borderRadius: '5px', fontSize: '11.5px', cursor: 'pointer', fontFamily: 'inherit' }"
                    >Forkast</button>
                    <button
                        type="button"
                        :disabled="form.processing || !dirty"
                        @click="save"
                        :style="{
                            background: 'var(--accent)',
                            color: '#fff',
                            border: 'none',
                            padding: '5px 11px',
                            borderRadius: '5px',
                            fontSize: '11.5px',
                            fontWeight: 500,
                            cursor: form.processing || !dirty ? 'not-allowed' : 'pointer',
                            opacity: form.processing || !dirty ? 0.55 : 1,
                            fontFamily: 'inherit',
                        }"
                    >Lagre</button>
                </div>
            </div>

            <div :style="{ maxWidth: '640px' }">
                <template v-if="loading">
                    <div v-for="i in 3" :key="i" :style="{ marginBottom: '20px' }">
                        <Skeleton :width="'100%'" :height="120" :radius="6" />
                    </div>
                </template>

                <template v-else>
                    <!-- Tilgang -->
                    <section v-show="active === 'access'">
                        <div :style="{ fontSize: '15px', fontWeight: 600, marginBottom: '3px', color: 'var(--fg)' }">Tilgang</div>
                        <div :style="{ fontSize: '12px', color: 'var(--fg-dim)', marginBottom: '16px' }">
                            Globale brytere som styrer om applikasjonen er tilgjengelig.
                        </div>
                        <div :style="{ display: 'flex', flexDirection: 'column', gap: '14px' }">
                            <div :style="{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '16px' }">
                                <div :style="{ flex: 1 }">
                                    <div :style="{ fontSize: '12.5px', fontWeight: 500, color: 'var(--fg)' }">Nettstedet er oppe</div>
                                    <div :style="{ fontSize: '11px', color: 'var(--fg-dim)', marginTop: '2px' }">
                                        Slår av &amp; på service. Administratorer har alltid tilgang.
                                    </div>
                                </div>
                                <Toggle v-model="form.site_up" label="Nettstedet er oppe" />
                            </div>

                            <div>
                                <div
                                    class="cmd-mono cmd-uc"
                                    :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '6px', letterSpacing: '0.06em' }"
                                >Vedlikeholdsmelding</div>
                                <input
                                    v-model="form.maintenance_message"
                                    type="text"
                                    :style="{
                                        width: '100%',
                                        background: 'var(--panel2)',
                                        border: '1px solid var(--border)',
                                        borderRadius: '5px',
                                        padding: '7px 10px',
                                        color: 'var(--fg)',
                                        fontSize: '12px',
                                        outline: 'none',
                                        fontFamily: 'inherit',
                                    }"
                                />
                                <div v-if="form.errors.maintenance_message" :style="{ color: 'var(--danger)', fontSize: '11px', marginTop: '4px' }">
                                    {{ form.errors.maintenance_message }}
                                </div>
                            </div>

                            <div :style="{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '16px' }">
                                <div :style="{ flex: 1 }">
                                    <div :style="{ fontSize: '12.5px', fontWeight: 500, color: 'var(--fg)' }">Innlogging aktivert</div>
                                    <div :style="{ fontSize: '11px', color: 'var(--fg-dim)', marginTop: '2px' }">
                                        La alle brukerinnlogginger skje som vanlig.
                                    </div>
                                </div>
                                <Toggle v-model="form.login_enabled" label="Innlogging aktivert" />
                            </div>

                            <div :style="{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '16px' }">
                                <div :style="{ flex: 1 }">
                                    <div :style="{ fontSize: '12.5px', fontWeight: 500, color: 'var(--fg)' }">Registrering åpen</div>
                                    <div :style="{ fontSize: '11px', color: 'var(--fg-dim)', marginTop: '2px' }">
                                        Tillat nye brukerregistreringer.
                                    </div>
                                </div>
                                <Toggle v-model="form.registration_open" label="Registrering åpen" />
                            </div>

                            <div :style="{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '16px' }">
                                <div :style="{ flex: 1 }">
                                    <div :style="{ fontSize: '12.5px', fontWeight: 500, color: 'var(--fg)' }">Krev e-postverifisering</div>
                                    <div :style="{ fontSize: '11px', color: 'var(--fg-dim)', marginTop: '2px' }">
                                        Brukere må verifisere e-post før tilgang.
                                    </div>
                                </div>
                                <Toggle v-model="form.require_email_verification" label="Krev e-postverifisering" />
                            </div>
                        </div>
                    </section>

                    <!-- Retningslinjer -->
                    <section v-show="active === 'policy'">
                        <div :style="{ fontSize: '15px', fontWeight: 600, marginBottom: '3px', color: 'var(--fg)' }">Retningslinjer</div>
                        <div :style="{ fontSize: '12px', color: 'var(--fg-dim)', marginBottom: '16px' }">
                            Standardverdier og krav for brukerkontoer.
                        </div>
                        <div :style="{ display: 'flex', flexDirection: 'column', gap: '14px' }">
                            <div>
                                <div
                                    class="cmd-mono cmd-uc"
                                    :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '6px', letterSpacing: '0.06em' }"
                                >Standardrolle ved registrering</div>
                                <div :style="{ position: 'relative' }">
                                    <button
                                        type="button"
                                        @click="roleOpen = !roleOpen"
                                        :style="{
                                            width: '100%',
                                            background: 'var(--panel2)',
                                            border: '1px solid var(--border)',
                                            borderRadius: '5px',
                                            padding: '7px 10px',
                                            color: 'var(--fg)',
                                            fontSize: '12px',
                                            cursor: 'pointer',
                                            display: 'flex',
                                            justifyContent: 'space-between',
                                            alignItems: 'center',
                                            fontFamily: 'inherit',
                                        }"
                                    >
                                        <span>{{ form.default_role }}</span>
                                        <Icon name="chevD" :size="12" />
                                    </button>
                                    <div
                                        v-if="roleOpen"
                                        :style="{
                                            position: 'absolute',
                                            top: '100%',
                                            left: 0,
                                            right: 0,
                                            marginTop: '2px',
                                            zIndex: 10,
                                            overflow: 'hidden',
                                            background: 'var(--panel)',
                                            border: '1px solid var(--border)',
                                            borderRadius: 'var(--radius-card)',
                                        }"
                                    >
                                        <div
                                            v-for="r in roles"
                                            :key="r"
                                            @click="form.default_role = r; roleOpen = false"
                                            :style="{
                                                padding: '7px 10px',
                                                fontSize: '12px',
                                                cursor: 'pointer',
                                                background: r === form.default_role ? 'var(--accent-soft)' : 'transparent',
                                                color: 'var(--fg)',
                                            }"
                                        >{{ r }}</div>
                                    </div>
                                </div>
                            </div>

                            <div :style="{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '16px' }">
                                <div :style="{ flex: 1 }">
                                    <div :style="{ fontSize: '12.5px', fontWeight: 500, color: 'var(--fg)' }">Krev 2FA for administratorer</div>
                                    <div :style="{ fontSize: '11px', color: 'var(--fg-dim)', marginTop: '2px' }">
                                        Admin uten 2FA blokkeres til det er aktivert.
                                    </div>
                                </div>
                                <Toggle v-model="form.require_2fa_for_admins" label="Krev 2FA for administratorer" />
                            </div>

                            <div :style="{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '16px' }">
                                <div :style="{ flex: 1 }">
                                    <div :style="{ fontSize: '12.5px', fontWeight: 500, color: 'var(--fg)' }">Send velkomstvarsling</div>
                                    <div :style="{ fontSize: '11px', color: 'var(--fg-dim)', marginTop: '2px' }">
                                        Send e-post ved registrering.
                                    </div>
                                </div>
                                <Toggle v-model="form.send_welcome_notification" label="Send velkomstvarsling" />
                            </div>
                        </div>
                    </section>

                    <!-- Annonsering -->
                    <section v-show="active === 'banner'">
                        <div :style="{ fontSize: '15px', fontWeight: 600, marginBottom: '3px', color: 'var(--fg)' }">Annonsering</div>
                        <div :style="{ fontSize: '12px', color: 'var(--fg-dim)', marginBottom: '16px' }">
                            Globalt banner som vises øverst for alle brukere.
                        </div>
                        <div :style="{ display: 'flex', flexDirection: 'column', gap: '14px' }">
                            <div>
                                <div
                                    class="cmd-mono cmd-uc"
                                    :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '6px', letterSpacing: '0.06em' }"
                                >Bannertekst</div>
                                <input
                                    v-model="form.announcement_banner"
                                    type="text"
                                    placeholder="(tom = skjult)"
                                    :style="{
                                        width: '100%',
                                        background: 'var(--panel2)',
                                        border: '1px solid var(--border)',
                                        borderRadius: '5px',
                                        padding: '7px 10px',
                                        color: 'var(--fg)',
                                        fontSize: '12px',
                                        outline: 'none',
                                        fontFamily: 'inherit',
                                    }"
                                />
                            </div>

                            <div>
                                <div
                                    class="cmd-mono cmd-uc"
                                    :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '6px', letterSpacing: '0.06em' }"
                                >Alvorlighetstone</div>
                                <div :style="{ display: 'inline-flex', gap: '1px', background: 'var(--border)', padding: '1px', borderRadius: '4px' }">
                                    <button
                                        v-for="o in severityOptions"
                                        :key="o.id"
                                        type="button"
                                        @click="form.announcement_severity = o.id"
                                        :style="{
                                            padding: '5px 14px',
                                            fontSize: '11px',
                                            cursor: 'pointer',
                                            fontFamily: 'inherit',
                                            borderRadius: '3px',
                                            border: 'none',
                                            background: form.announcement_severity === o.id ? 'var(--panel2)' : 'var(--panel)',
                                            color: form.announcement_severity === o.id ? 'var(--fg)' : 'var(--fg-dim)',
                                        }"
                                    >{{ o.label }}</button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Filsystem -->
                    <section v-show="active === 'fs'">
                        <div :style="{ fontSize: '15px', fontWeight: 600, marginBottom: '3px', color: 'var(--fg)' }">Filsystem</div>
                        <div :style="{ fontSize: '12px', color: 'var(--fg-dim)', marginBottom: '16px' }">
                            Innstillinger for personlig lagring.
                        </div>
                        <div :style="{ display: 'flex', flexDirection: 'column', gap: '14px' }">
                            <div :style="{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '16px' }">
                                <div :style="{ flex: 1 }">
                                    <div :style="{ fontSize: '12.5px', fontWeight: 500, color: 'var(--fg)' }">Aktiver personlig filsystem</div>
                                    <div :style="{ fontSize: '11px', color: 'var(--fg-dim)', marginTop: '2px' }">
                                        Gi brukere et privat lagringsområde.
                                    </div>
                                </div>
                                <Toggle v-model="form.files_feature_enabled" label="Aktiver personlig filsystem" />
                            </div>

                            <div>
                                <div
                                    class="cmd-mono cmd-uc"
                                    :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '6px', letterSpacing: '0.06em' }"
                                >Maks slettetid (dager)</div>
                                <input
                                    v-model.number="form.max_share_days"
                                    type="number"
                                    min="1"
                                    max="30"
                                    class="cmd-mono"
                                    :style="{
                                        width: '90px',
                                        background: 'var(--panel2)',
                                        border: '1px solid var(--border)',
                                        borderRadius: '5px',
                                        padding: '7px 10px',
                                        color: 'var(--fg)',
                                        fontSize: '12px',
                                        outline: 'none',
                                    }"
                                />
                            </div>

                            <div>
                                <div
                                    class="cmd-mono cmd-uc"
                                    :style="{ fontSize: '10px', color: 'var(--fg-mute)', marginBottom: '6px', letterSpacing: '0.06em' }"
                                >Standard personlig lagring (bytes)</div>
                                <input
                                    v-model.number="defaultPersonalStorageBytes"
                                    type="number"
                                    min="-1"
                                    class="cmd-mono"
                                    placeholder="tom = ubegrenset, -1 = ubegrenset, 0 = blokkert"
                                    :style="{
                                        width: '100%',
                                        maxWidth: '360px',
                                        background: 'var(--panel2)',
                                        border: '1px solid var(--border)',
                                        borderRadius: '5px',
                                        padding: '7px 10px',
                                        color: 'var(--fg)',
                                        fontSize: '12px',
                                        outline: 'none',
                                    }"
                                />
                                <p :style="{ fontSize: '11px', color: 'var(--fg-mute)', marginTop: '4px' }">
                                    Standard byte-grense for alle brukeres personlige filer. Kan overstyres per kunde og per bruker.
                                </p>
                            </div>
                        </div>
                    </section>
                </template>
            </div>
        </div>
    </div>
</template>
