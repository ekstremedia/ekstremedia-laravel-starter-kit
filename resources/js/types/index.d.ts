import type { PageProps as InertiaPageProps } from '@inertiajs/core';

export type UserSettingValue = string | number | boolean | null;

export interface User {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    email_verified_at?: string;
    two_factor_enabled?: boolean;
    created_at?: string;
    full_name: string;
    avatar_url?: string | null;
    avatar_thumb_url?: string | null;
    roles?: string[];
    permissions?: string[];
    unread_notifications_count?: number;
    unread_messages_count?: number;
    is_impersonating?: boolean;
}

export type NotificationDigestFrequency = 'none' | 'daily' | 'weekly';

export interface UserSettings {
    locale: string;
    dark_mode: boolean;
    notification_email_immediate: boolean;
    notification_digest: NotificationDigestFrequency;
    notification_chat_messages: boolean;
    notification_account_updates: boolean;
    notification_system_alerts: boolean;
    [key: string]: UserSettingValue;
}

export interface Customer {
    id: number;
    slug: string;
    name: string;
}

export interface PageProps extends InertiaPageProps {
    auth: {
        user?: User;
    };
    debug: {
        easy_login_enabled: boolean;
    };
    locale: string;
    user_settings: UserSettings;
    flash: {
        success?: string;
        error?: string;
        status?: string;
    };
    tenancy: {
        enabled: boolean;
    };
    chat: {
        enabled: boolean;
    };
    customer: Customer | null;
    customers: Customer[];
}
