/*
 * Global keyboard layer for the Command shell.
 *
 * - ⌘K / Ctrl-K → toggle command palette (caller provides the toggle).
 * - G then H/D/U/A within 700 ms → navigate (Home / Dashbord / Brukere /
 *   Appinnstillinger). G is not acted on directly — the next key decides.
 * - ? → help toast listing shortcuts.
 * - Esc → close palette.
 *
 * Keyboard handlers must ignore keys typed into INPUT / TEXTAREA / SELECT or
 * contenteditable surfaces so typing 'g' in a search box doesn't pop the nav.
 * ⌘K is the one exception — the palette trigger fires even from an input.
 */

import { onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useCommandToasts } from './useCommandToasts';

interface Options {
    onTogglePalette: () => void;
    onClosePalette: () => void;
}

const ROUTES: Record<string, string> = {
    h: '/home',
    d: '/admin',
    u: '/admin/users',
    a: '/admin/settings',
};

function isTypingTarget(el: EventTarget | null): boolean {
    if (!(el instanceof HTMLElement)) return false;
    const tag = el.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return true;
    return el.isContentEditable;
}

export function useCommandKeyboard(opts: Options) {
    const { push } = useCommandToasts();
    const { t } = useI18n();
    let seq = '';
    let seqTimer: number | null = null;

    function resetSeq() {
        seq = '';
        if (seqTimer !== null) {
            window.clearTimeout(seqTimer);
            seqTimer = null;
        }
    }

    function onKeyDown(e: KeyboardEvent) {
        const typing = isTypingTarget(e.target);

        // ⌘K / Ctrl-K always works — including from inside inputs.
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            opts.onTogglePalette();
            return;
        }

        if (e.key === 'Escape') {
            opts.onClosePalette();
            return;
        }

        if (typing) return;
        if (e.metaKey || e.ctrlKey || e.altKey) return;

        const key = e.key.toLowerCase();

        if (key === '?') {
            push(t('palette.shortcuts_hint'), 'info', 4500);
            return;
        }

        if (seq === 'g' && ROUTES[key]) {
            e.preventDefault();
            router.visit(ROUTES[key]);
            resetSeq();
            return;
        }

        if (key === 'g') {
            resetSeq();
            seq = 'g';
            seqTimer = window.setTimeout(resetSeq, 700);
            return;
        }

        // Any other key after g resets the sequence.
        if (seq) resetSeq();
    }

    onMounted(() => {
        window.addEventListener('keydown', onKeyDown);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', onKeyDown);
        resetSeq();
    });
}
