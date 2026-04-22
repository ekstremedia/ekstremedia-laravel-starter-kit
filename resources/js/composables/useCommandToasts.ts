/*
 * Command-style toast queue — separate from PrimeVue's Toast because the
 * design calls for a bottom-right monospace pill with a leading dot indicator
 * that doesn't map cleanly onto PrimeVue's default severity skin.
 *
 * PrimeVue's Toast stays available elsewhere; flash-driven toasts still flow
 * through useFlashToast + PrimeVue. Command-aware surfaces (palette, inline
 * role edit, save buttons) call push() here.
 */

import { ref } from 'vue';

export type CommandToastTone = 'info' | 'success' | 'warning' | 'danger';

export interface CommandToast {
    id: number;
    msg: string;
    tone: CommandToastTone;
}

const toasts = ref<CommandToast[]>([]);
let seq = 0;

export function useCommandToasts() {
    function push(msg: string, tone: CommandToastTone = 'info', life = 2800) {
        const id = ++seq;
        toasts.value = [...toasts.value, { id, msg, tone }];
        window.setTimeout(() => {
            toasts.value = toasts.value.filter((t) => t.id !== id);
        }, life);
    }

    function remove(id: number) {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    }

    return { toasts, push, remove };
}
