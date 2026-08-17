import { departmentPalette } from '../../resources/js/Support/departmentColors';

test('keeps arbitrary hex accents readable on their light tint', () => {
    expect(departmentPalette('#FFFF00')).toEqual({
        accent: '#FFFF00',
        tint: 'color-mix(in srgb, #FFFF00 12%, white)',
        foreground: '#17212B',
    });
});

test('does not treat prototype keys as configured palettes', () => {
    const palette = departmentPalette('__proto__', 'department-1');

    expect(palette.accent).toMatch(/^#/);
    expect(palette.tint).toMatch(/^#/);
    expect(palette.foreground).toMatch(/^#/);
});

test('assigns a stable fallback palette when a department has no color', () => {
    expect(departmentPalette(null, 'department-7')).toEqual(departmentPalette(undefined, 'department-7'));
});
