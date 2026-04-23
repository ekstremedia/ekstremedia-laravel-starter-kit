/*
 * Command design-system tweaks: theme / accent / density / kbd-hint toggle.
 *
 * State is persisted to the existing `starter_kit_settings` localStorage key
 * alongside dark_mode / locale — the inline pre-hydration script in
 * app.blade.php reads these same keys so first paint lands on the correct
 * theme. `.dark` class is kept in sync so PrimeVue's dialog / datatable dark
 * overrides stay active when theme is dark or hc.
 */

import { ref, watch } from 'vue';
import type { CommandAccent, CommandDensity, CommandTheme } from '@/types';

const STORAGE_KEY = import.meta.env.VITE_APP_STORAGE_KEY || 'starter_kit_settings';

interface TweaksState {
    theme: CommandTheme;
    accent: CommandAccent;
    density: CommandDensity;
    showKbdHints: boolean;
    railExpanded: boolean;
}

const defaults: TweaksState = {
    theme: 'dark',
    accent: 'cobalt',
    density: 'comfortable',
    showKbdHints: true,
    railExpanded: false,
};

function readStored(): TweaksState {
    if (typeof localStorage === 'undefined') return { ...defaults };
    try {
        const raw = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        return {
            theme: (['dark', 'hc', 'light'] as const).includes(raw.theme) ? raw.theme : defaults.theme,
            accent: (['cobalt', 'emerald', 'amber', 'violet'] as const).includes(raw.accent) ? raw.accent : defaults.accent,
            density: (['compact', 'comfortable', 'relaxed'] as const).includes(raw.density) ? raw.density : defaults.density,
            showKbdHints: typeof raw.show_kbd_hints === 'boolean' ? raw.show_kbd_hints : defaults.showKbdHints,
            railExpanded: typeof raw.rail_expanded === 'boolean' ? raw.rail_expanded : defaults.railExpanded,
        };
    } catch {
        return { ...defaults };
    }
}

const state = ref<TweaksState>(readStored());

function apply(s: TweaksState) {
    if (typeof document === 'undefined') return;
    const root = document.documentElement;
    root.setAttribute('data-theme', s.theme);
    root.setAttribute('data-accent', s.accent);
    root.setAttribute('data-density', s.density);
    root.style.setProperty('--rail-w', s.railExpanded ? '180px' : '52px');
    // PrimeVue reads `.dark` — keep it for dark & hc so existing overrides keep
    // painting. Light drops the class so PrimeVue renders its default palette.
    if (s.theme === 'light') {
        root.classList.remove('dark');
    } else {
        root.classList.add('dark');
    }
}

function persist(s: TweaksState) {
    if (typeof localStorage === 'undefined') return;
    try {
        const existing = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({
                ...existing,
                theme: s.theme,
                accent: s.accent,
                density: s.density,
                show_kbd_hints: s.showKbdHints,
                rail_expanded: s.railExpanded,
                // Keep dark_mode in sync with the legacy flag so the pre-hydrate
                // script + DarkModeToggle composable don't drift.
                dark_mode: s.theme !== 'light',
            }),
        );
    } catch {
        // localStorage blocked — tweak still applies for the session.
    }
}

// Eagerly apply the stored theme + register the persistence watcher at module
// load time. We used to gate this on a `let initialized = false` flag flipped
// by the first `useTweaks()` call, but under Vite HMR the module re-evaluates
// and creates a new `state` ref — a component that was mounted before the
// reload kept mutating the old ref while the new module's watch was never
// registered (nothing calls `useTweaks()` during HMR until a component
// re-renders). Running the watch binding at module scope means every module
// evaluation — initial load and every HMR — gets a matched state/watch pair.
apply(state.value);
watch(
    state,
    (s) => {
        apply(s);
        persist(s);
    },
    { deep: true },
);

export function useTweaks() {
    function setTheme(theme: CommandTheme) { state.value = { ...state.value, theme }; }
    function setAccent(accent: CommandAccent) { state.value = { ...state.value, accent }; }
    function setDensity(density: CommandDensity) { state.value = { ...state.value, density }; }
    function setShowKbdHints(v: boolean) { state.value = { ...state.value, showKbdHints: v }; }
    function setRailExpanded(v: boolean) { state.value = { ...state.value, railExpanded: v }; }
    function toggleRail() { state.value = { ...state.value, railExpanded: !state.value.railExpanded }; }

    return { state, setTheme, setAccent, setDensity, setShowKbdHints, setRailExpanded, toggleRail };
}
