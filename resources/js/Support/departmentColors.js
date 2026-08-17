const namedDepartmentColors = {
    'fuchsia pink': { accent: '#C0267D', tint: '#FCE7F3', foreground: '#831843' },
    red: { accent: '#C9362B', tint: '#FDE9E7', foreground: '#99231F' },
    yellow: { accent: '#BD8B00', tint: '#FFF6D5', foreground: '#785900' },
    purple: { accent: '#7C3AED', tint: '#F2EAFF', foreground: '#5B21B6' },
    gray: { accent: '#687078', tint: '#EEF1F2', foreground: '#4B5563' },
    grey: { accent: '#687078', tint: '#EEF1F2', foreground: '#4B5563' },
    blue: { accent: '#1D4ED8', tint: '#E8F0FF', foreground: '#1E3A8A' },
    green: { accent: '#16845B', tint: '#E7F5EE', foreground: '#12623F' },
};

const fallbackPalettes = [
    { accent: '#0B536D', tint: '#E6F2F5', foreground: '#0B536D' },
    { accent: '#A65D00', tint: '#FFF1D6', foreground: '#824900' },
    { accent: '#3E6B3A', tint: '#EAF4E8', foreground: '#31552F' },
    { accent: '#7B3F72', tint: '#F7EAF6', foreground: '#66345E' },
    { accent: '#8B3A3A', tint: '#FCEBEC', foreground: '#7A2C2C' },
    { accent: '#315A8A', tint: '#E7F0FB', foreground: '#284A72' },
    { accent: '#6A5B2C', tint: '#F2ECDE', foreground: '#5B4E23' },
    { accent: '#2E6E6A', tint: '#E5F3F1', foreground: '#245B58' },
];

const readableForeground = '#17212B';

function normalize(value) {
    return String(value ?? '').trim().toLocaleLowerCase();
}

function stableHash(value) {
    return Array.from(String(value ?? '')).reduce((hash, character) => ((hash * 31) + character.charCodeAt(0)) >>> 0, 7);
}

export function departmentPalette(color, seed = 'department') {
    const normalized = normalize(color);

    if (Object.prototype.hasOwnProperty.call(namedDepartmentColors, normalized)) {
        return namedDepartmentColors[normalized];
    }

    if (/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(normalized)) {
        const accent = normalized.toUpperCase();
        return {
            accent,
            tint: `color-mix(in srgb, ${accent} 12%, white)`,
            foreground: readableForeground,
        };
    }

    return fallbackPalettes[stableHash(seed || normalized) % fallbackPalettes.length];
}
