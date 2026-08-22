import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const appCss = readFileSync(resolve(projectRoot, 'resources/css/app.css'), 'utf8');

describe('Tailwind v4 configuration', () => {
    it('uses the CSS-first compiler contract', () => {
        expect(appCss).toContain('@import "tailwindcss";');
        expect(appCss).toContain('@theme inline');
        expect(appCss).not.toContain('@custom-variant dark');
        expect(appCss).not.toContain('html.dark');
        expect(appCss).toContain('@plugin "@tailwindcss/forms";');
        expect(appCss).toContain('@source "../js";');
        expect(appCss).not.toContain('@tailwind base;');
        expect(existsSync(resolve(projectRoot, 'tailwind.config.js'))).toBe(false);
    });

    it('keeps action text and interactive boundaries at accessible contrast', () => {
        expect(appCss).toContain('--primary: #197f9d;');
        expect(appCss).toContain('--control-border: #76869b;');
        expect(appCss).toContain('--color-control-border: var(--control-border);');
    });
});
