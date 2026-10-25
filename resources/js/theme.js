/**
 * ORBIT theme preference (ADR-0008).
 *
 * The default is the light theme. A visitor may switch to the deep teal /
 * midnight plum theme ("plum"). The choice is stored in localStorage and is
 * applied before first paint by the inline script in the <head> (see
 * resources/views/components/layouts/partials/head.blade.php) so there is no
 * flash of the wrong theme.
 */

export const STORAGE_KEY = 'orbit-theme';
export const THEMES = ['light', 'plum'];

export function readStoredTheme() {
    try {
        const value = window.localStorage.getItem(STORAGE_KEY);

        return THEMES.includes(value) ? value : null;
    } catch {
        return null;
    }
}

export function applyTheme(theme) {
    const root = document.documentElement;

    if (theme === 'plum') {
        root.setAttribute('data-theme', 'plum');
    } else {
        root.removeAttribute('data-theme');
    }

    try {
        window.localStorage.setItem(STORAGE_KEY, theme);
    } catch {
        // Storage may be unavailable (private mode, disabled). The toggle still works for this page.
    }
}

export function theme() {
    return {
        theme: readStoredTheme() ?? 'light',

        get isPlum() {
            return this.theme === 'plum';
        },

        get label() {
            return this.isPlum ? 'Switch to light theme' : 'Switch to deep teal theme';
        },

        toggle() {
            this.set(this.isPlum ? 'light' : 'plum');
        },

        set(value) {
            this.theme = THEMES.includes(value) ? value : 'light';
            applyTheme(this.theme);
        },
    };
}
