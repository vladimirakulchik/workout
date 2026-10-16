const DEFAULT_BRAND = '#00B300';

const HEX_PATTERN = /^#?([0-9a-f]{6})$/i;

export function isValidHex(hex: string): boolean {
    return HEX_PATTERN.test(hex.trim());
}

export function hexToRgb(hex: string): [number, number, number] {
    const match = HEX_PATTERN.exec(hex.trim());

    if (!match) {
        throw new Error(`Invalid hex colour: ${hex}`);
    }

    const value = parseInt(match[1], 16);

    return [(value >> 16) & 255, (value >> 8) & 255, value & 255];
}

function rgbToHex([r, g, b]: [number, number, number]): string {
    return (
        '#' +
        [r, g, b]
            .map((channel) =>
                Math.max(0, Math.min(255, Math.round(channel)))
                    .toString(16)
                    .padStart(2, '0'),
            )
            .join('')
    );
}

export function lightenColor(hex: string, percent: number): string {
    const [r, g, b] = hexToRgb(hex);
    const ratio = percent / 100;

    return rgbToHex([
        r + (255 - r) * ratio,
        g + (255 - g) * ratio,
        b + (255 - b) * ratio,
    ]);
}

export function darkenColor(hex: string, percent: number): string {
    const [r, g, b] = hexToRgb(hex);
    const ratio = 1 - percent / 100;

    return rgbToHex([r * ratio, g * ratio, b * ratio]);
}

const BRAND_VARIABLES = [
    '--brand-primary',
    '--brand-primary-soft',
    '--brand-primary-deep',
    '--brand-primary-rgb',
] as const;

/**
 * Apply an accent colour to the document, or reset to the default brand
 * (defined in app.css) when given null or an invalid value.
 */
export function applyBrand(hex: string | null): void {
    if (typeof document === 'undefined') {
        return;
    }

    const style = document.documentElement.style;

    if (hex === null || !isValidHex(hex)) {
        BRAND_VARIABLES.forEach((name) => style.removeProperty(name));

        return;
    }

    const normalized = hex.startsWith('#') ? hex : `#${hex}`;

    style.setProperty('--brand-primary', normalized);
    style.setProperty('--brand-primary-soft', lightenColor(normalized, 20));
    style.setProperty('--brand-primary-deep', darkenColor(normalized, 20));
    style.setProperty('--brand-primary-rgb', hexToRgb(normalized).join(', '));
}

export { DEFAULT_BRAND };
