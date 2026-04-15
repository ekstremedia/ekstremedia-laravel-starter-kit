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
    is_impersonating?: boolean;
}

export interface UserSettings {
    locale: string;
    dark_mode: boolean;
    [key: string]: UserSettingValue;
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
}
