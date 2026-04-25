<script setup lang="ts">
/*
 * Customer "About" landing — the company's profile card. Members of the
 * customer can view this. Customer Admins (and SuperAdmins) get an Edit
 * button that takes them to /c/{slug}/about/edit.
 */
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLayout from '@/Layouts/CommandLayout.vue';
import Icon from '@/Components/Command/Icon.vue';
import { useCustomer } from '@/composables/useCustomer';

interface CustomerProfilePayload {
    id: number;
    slug: string;
    name: string;
    headline: string | null;
    about: string | null;
    location: string | null;
    website: string | null;
}

interface Member {
    public_id: string;
    full_name: string;
    headline: string | null;
    avatar_thumb_url: string | null;
    roles: string[];
}

interface Props {
    profile: CustomerProfilePayload;
    members: Member[];
    member_count: number;
    can_edit: boolean;
}

const props = defineProps<Props>();
const { t } = useI18n();
const { customerUrl } = useCustomer();

const websiteHostname = computed(() => {
    if (!props.profile.website) return null;
    try {
        return new URL(props.profile.website).hostname.replace(/^www\./, '');
    } catch {
        return props.profile.website;
    }
});

function memberInitials(fullName: string): string {
    const parts = fullName.trim().split(/\s+/);
    return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase();
}
</script>

<template>
    <AppLayout>
        <Head :title="profile.name" />

        <div :style="{ maxWidth: '880px', margin: '0 auto', padding: '24px 20px', display: 'flex', flexDirection: 'column', gap: '18px' }">
            <div class="cmd-card" :style="{ padding: '24px', display: 'flex', flexDirection: 'column', gap: '8px' }">
                <div :style="{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '12px' }">
                    <div :style="{ display: 'flex', flexDirection: 'column', gap: '4px', minWidth: 0 }">
                        <h1 :style="{ margin: 0, fontSize: '22px', fontWeight: 600, color: 'var(--fg)' }">{{ profile.name }}</h1>
                        <p
                            v-if="profile.headline"
                            :style="{ margin: 0, fontSize: '13px', color: 'var(--fg-dim)' }"
                        >{{ profile.headline }}</p>
                        <div :style="{ display: 'flex', flexWrap: 'wrap', gap: '12px', marginTop: '6px', fontSize: '12px', color: 'var(--fg-mute)' }">
                            <span v-if="profile.location" :style="{ display: 'inline-flex', alignItems: 'center', gap: '5px' }">
                                <Icon name="customer" :size="12" />{{ profile.location }}
                            </span>
                            <a
                                v-if="profile.website"
                                :href="profile.website"
                                target="_blank"
                                rel="nofollow noopener"
                                :style="{ display: 'inline-flex', alignItems: 'center', gap: '5px', color: 'var(--accent)', textDecoration: 'none' }"
                            >
                                <Icon name="link" :size="12" />{{ websiteHostname }}
                            </a>
                        </div>
                    </div>
                    <Link
                        v-if="can_edit"
                        :href="customerUrl('/about/edit')"
                        :style="{
                            background: 'var(--panel2)',
                            color: 'var(--fg)',
                            border: '1px solid var(--border)',
                            padding: '6px 12px',
                            borderRadius: '5px',
                            fontSize: '12px',
                            textDecoration: 'none',
                            whiteSpace: 'nowrap',
                        }"
                    >
                        <Icon name="edit" :size="11" /> {{ t('customer_about.edit') }}
                    </Link>
                </div>
            </div>

            <div v-if="profile.about" class="cmd-card" :style="{ padding: '20px' }">
                <h2 :style="{ margin: '0 0 10px', fontSize: '13px', fontWeight: 600, color: 'var(--fg)' }">{{ t('customer_about.title') }}</h2>
                <p :style="{ margin: 0, fontSize: '13px', lineHeight: 1.55, color: 'var(--fg-dim)', whiteSpace: 'pre-wrap' }">{{ profile.about }}</p>
            </div>

            <div class="cmd-card" :style="{ padding: '20px' }">
                <div :style="{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '12px' }">
                    <h2 :style="{ margin: 0, fontSize: '13px', fontWeight: 600, color: 'var(--fg)' }">{{ t('customer_about.members') }}</h2>
                    <span :style="{ fontSize: '11px', color: 'var(--fg-mute)' }">{{ t('customer_about.member_count', { count: member_count }) }}</span>
                </div>
                <ul
                    v-if="members.length"
                    :style="{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: '8px' }"
                >
                    <li v-for="m in members" :key="m.public_id">
                        <Link
                            :href="`/u/${m.public_id}`"
                            :style="{ display: 'flex', alignItems: 'center', gap: '10px', padding: '8px', borderRadius: '6px', textDecoration: 'none', background: 'var(--panel2)', border: '1px solid var(--border)' }"
                        >
                            <div
                                :style="{
                                    width: '32px',
                                    height: '32px',
                                    borderRadius: '8px',
                                    background: 'var(--panel)',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    overflow: 'hidden',
                                    flexShrink: 0,
                                }"
                            >
                                <img
                                    v-if="m.avatar_thumb_url"
                                    :src="m.avatar_thumb_url"
                                    :alt="m.full_name"
                                    :style="{ width: '100%', height: '100%', objectFit: 'cover' }"
                                />
                                <span v-else :style="{ fontSize: '11px', color: 'var(--fg-mute)', fontWeight: 600 }">{{ memberInitials(m.full_name) }}</span>
                            </div>
                            <div :style="{ minWidth: 0, flex: 1 }">
                                <div :style="{ fontSize: '12.5px', color: 'var(--fg)', fontWeight: 500, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }">{{ m.full_name }}</div>
                                <div :style="{ fontSize: '11px', color: 'var(--fg-mute)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }">{{ m.headline || (m.roles[0] ?? t('customer_about.role_member')) }}</div>
                            </div>
                        </Link>
                    </li>
                </ul>
                <p v-else :style="{ fontSize: '12px', color: 'var(--fg-mute)', margin: 0 }">{{ t('customer_about.no_members') }}</p>
            </div>
        </div>
    </AppLayout>
</template>
