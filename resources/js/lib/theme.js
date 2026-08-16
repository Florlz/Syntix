export function resolveTheme(theme) {
    if (theme === 'dark') return 'dark';
    if (theme === 'light') return 'light';

    return window.matchMedia?.('(prefers-color-scheme: dark)')?.matches ? 'dark' : 'light';
}

export function applyTheme(theme) {
    const root = document.documentElement;
    const dark = resolveTheme(theme) === 'dark';

    root.classList.toggle('dark', dark);
    root.style.colorScheme = dark ? 'dark' : 'light';
}

export function clearTheme() {
    const root = document.documentElement;

    root.classList.remove('dark');
    root.style.colorScheme = '';
}
