/**
 * ORBIT theme preference (ADR-0008).
 *
 * The default is the light theme. A visitor may switch to the deep teal /
 * midnight plum theme ("plum"). The choice is stored in localStorage and is
 * applied before first paint by the inline script in the <head> (see
 * resources/views/components/layouts/partials/head.blade.php) so there is no
 * flash of the wrong theme.
 *
 * When the organisation locks the theme, this controller refuses to switch.
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

export function readThemeLock() {
    return document.documentElement.dataset.themeLock || 'user';
}

export function applyTheme(theme, { persist = true } = {}) {
    const root = document.documentElement;

    if (theme === 'plum') {
        root.setAttribute('data-theme', 'plum');
    } else {
        root.removeAttribute('data-theme');
    }

    if (! persist) {
        return;
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

        get lock() {
            return readThemeLock();
        },

        get isLocked() {
            return this.lock === 'plum' || this.lock === 'light';
        },

        get isPlum() {
            return this.theme === 'plum';
        },

        get label() {
            if (this.isLocked) {
                return this.isPlum ? 'Deep teal theme' : 'Light theme';
            }

            return this.isPlum ? 'Switch to light theme' : 'Switch to deep teal theme';
        },

        init() {
            if (this.lock === 'plum' || this.lock === 'light') {
                this.theme = this.lock;
                applyTheme(this.theme, { persist: false });

                return;
            }

            this.theme = readStoredTheme() ?? 'light';
        },

        toggle() {
            if (this.isLocked) {
                return;
            }

            this.set(this.isPlum ? 'light' : 'plum');
        },

        set(value) {
            if (this.isLocked) {
                return;
            }

            this.theme = THEMES.includes(value) ? value : 'light';
            applyTheme(this.theme);
        },
    };
}
