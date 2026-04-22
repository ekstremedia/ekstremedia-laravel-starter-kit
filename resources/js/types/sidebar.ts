import type { IconName } from '@/Components/Command/Icon.vue';

export interface SidebarItem {
    id: string;
    href: string;
    label: string;
    icon: IconName;
    kb?: string;
    match: (path: string) => boolean;
    hideWhen?: () => boolean;
}

export interface SidebarSeparator {
    separator: true;
    key: string;
}

export type SidebarEntry = SidebarItem | SidebarSeparator;

export function isSidebarItem(entry: SidebarEntry): entry is SidebarItem {
    return !('separator' in entry);
}
